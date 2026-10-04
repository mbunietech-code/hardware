import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../data/api_client.dart';
import '../data/db.dart';
import '../data/local_store.dart';
import '../data/models.dart';
import '../data/sync_engine.dart';
import '../l10n/l10n.dart';

/// Where the API token is kept (secure storage on devices, memory in tests).
abstract class TokenStorage {
  Future<String?> read();
  Future<void> write(String? token);
}

class SecureTokenStorage implements TokenStorage {
  const SecureTokenStorage();
  static const _storage = FlutterSecureStorage();
  @override
  Future<String?> read() => _storage.read(key: 'api_token');
  @override
  Future<void> write(String? token) => token == null ? _storage.delete(key: 'api_token') : _storage.write(key: 'api_token', value: token);
}

class MemoryTokenStorage implements TokenStorage {
  String? token;
  @override
  Future<String?> read() async => token;
  @override
  Future<void> write(String? t) async => token = t;
}

/// Default server address; changeable on the login screen.
/// 10.0.2.2 reaches the host machine from the Android emulator.
const defaultServerUrl = 'http://10.0.2.2:8765';

class AppState extends ChangeNotifier {
  AppState({required this.db, TokenStorage? tokens, this.watchConnectivity = true})
    : store = LocalStore(db),
      _tokens = tokens ?? const SecureTokenStorage();

  final AppDb db;
  final LocalStore store;
  final TokenStorage _tokens;
  final bool watchConnectivity;

  late ApiClient api;
  late SyncEngine sync;
  Map<String, dynamic>? user;
  String serverUrl = defaultServerUrl;
  int? selectedShopId;
  bool online = false;
  bool syncing = false;
  int unsynced = 0;
  Map<String, int> queueCounts = {};
  String? lastSyncMessage;
  DateTime? lastSyncAt;
  DailySession? todaySession;
  bool sessionExpired = false;
  int revision = 0; // bumped after data changes so screens can reload
  String language = 'en';

  StreamSubscription<List<ConnectivityResult>>? _connSub;
  Timer? _timer;

  bool get loggedIn => user != null;
  bool get isSuperAdmin => user?['role'] == 'super_admin';
  String get currency => (user?['currency'] as String?) ?? 'TZS';
  String get businessName => (user?['business_name'] as String?) ?? 'Hardware BMS';
  int? get shopId => (user?['shop_id'] as int?) ?? selectedShopId;
  bool can(String permission) => isSuperAdmin || ((user?['permissions'] as Map?)?[permission] == true);

  Future<void> init() async {
    var deviceId = await db.getKv('device_id');
    if (deviceId == null) {
      deviceId = store.newUuid();
      await db.setKv('device_id', deviceId);
    }
    serverUrl = await db.getKv('server_url') ?? defaultServerUrl;
    language = await db.getKv('language') ?? 'en';
    L10n.lang = language;
    final token = await _tokens.read();
    final userJson = await db.getKv('user');
    selectedShopId = int.tryParse(await db.getKv('shop_id') ?? '');
    api = ApiClient(baseUrl: serverUrl, token: token, deviceId: deviceId);
    sync = SyncEngine(store, api);
    if (userJson != null) {
      user = Map<String, dynamic>.from(jsonDecode(userJson) as Map);
      store.userId = user!['id'] as int?;
      sessionExpired = token == null;
    }
    if (watchConnectivity) {
      _connSub = Connectivity().onConnectivityChanged.listen((r) {
        final nowOnline = r.any((c) => c != ConnectivityResult.none);
        if (nowOnline && !online) unawaited(syncNow());
        online = nowOnline;
        notifyListeners();
      });
      final r = await Connectivity().checkConnectivity();
      online = r.any((c) => c != ConnectivityResult.none);
    }
    _timer = Timer.periodic(const Duration(minutes: 1), (_) {
      if (loggedIn && !sessionExpired) unawaited(syncNow(quiet: true));
    });
    await refresh();
    if (loggedIn && !sessionExpired) unawaited(syncNow(quiet: true));
  }

  /// Reload counters and today's session from SQLite.
  Future<void> refresh() async {
    unsynced = await store.unsyncedCount();
    queueCounts = await store.queueCounts();
    todaySession = shopId == null ? null : await store.session(shopId!, today());
    revision++;
    notifyListeners();
  }

  Future<void> login(String url, String login, String password) async {
    serverUrl = url.trim();
    api.baseUrl = serverUrl;
    api.token = null;
    final res = await api.login(login.trim(), password, deviceName: '${Platform.operatingSystem} device', platform: Platform.operatingSystem);
    final newUser = Map<String, dynamic>.from(res['user'] as Map);
    final previousUser = await db.getKv('user');
    if (previousUser != null) {
      final prevId = (jsonDecode(previousUser) as Map)['id'];
      if (prevId != newUser['id'] && await store.unsyncedCount() > 0) {
        throw ApiException(tr('Another user has records on this phone that are not synced yet. They must log in and sync first.'));
      }
      if (prevId != newUser['id']) await db.setKv('last_pull', null);
    }
    api.token = res['token'] as String;
    await _tokens.write(api.token);
    await db.setKv('server_url', serverUrl);
    await db.setKv('user', jsonEncode(newUser));
    user = newUser;
    store.userId = newUser['id'] as int?;
    sessionExpired = false;
    online = true;
    await db.setKv('last_pull', null); // full refresh after login
    await syncNow();
  }

  /// Logging out keeps unsynced records on the device; they sync after the next login.
  Future<void> logout() async {
    try {
      if (api.token != null) await api.post('auth/logout');
    } on ApiException catch (_) {
      // Offline logout is fine.
    }
    api.token = null;
    await _tokens.write(null);
    if (await store.unsyncedCount() == 0) {
      await db.setKv('user', null);
      user = null;
    } else {
      sessionExpired = true; // keep the user so their queue stays attributed
    }
    notifyListeners();
  }

  /// Switch the UI language (en / sw). Saved on the phone; server messages follow via Accept-Language.
  Future<void> setLanguage(String lang) async {
    if (!L10n.supported.containsKey(lang)) return;
    language = lang;
    L10n.lang = lang;
    await db.setKv('language', lang);
    revision++;
    notifyListeners();
  }

  Future<void> toggleLanguage() => setLanguage(language == 'sw' ? 'en' : 'sw');

  Future<void> selectShop(int id) async {
    selectedShopId = id;
    await db.setKv('shop_id', '$id');
    await refresh();
  }

  Future<SyncReport?> syncNow({bool quiet = false}) async {
    if (!loggedIn || syncing || sessionExpired) return null;
    syncing = true;
    if (!quiet) notifyListeners();
    SyncReport? report;
    try {
      report = await sync.run();
      if (report.ok) {
        online = true;
        lastSyncAt = DateTime.now();
      } else if (report.error != null) {
        online = false;
      }
      lastSyncMessage = report.toString();
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        sessionExpired = true;
        api.token = null;
        await _tokens.write(null);
        lastSyncMessage = tr('Session expired. Log in again to continue syncing.');
      } else {
        lastSyncMessage = e.message;
      }
    } finally {
      syncing = false;
      await refresh();
    }
    return report;
  }

  /// Call after saving a record: refresh UI and try to sync right away.
  Future<void> recorded() async {
    await refresh();
    unawaited(syncNow(quiet: true));
  }

  @override
  void dispose() {
    _connSub?.cancel();
    _timer?.cancel();
    super.dispose();
  }
}
