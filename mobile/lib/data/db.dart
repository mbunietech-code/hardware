import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Local SQLite database (offline-first, Doc 09).
///
/// Server data pulled for offline use is cached in catalog tables. Everything the
/// user records is written to [sync_queue] first, with a local_uuid generated
/// before the first save, and stays there (with its sync status) after syncing.
class AppDb {
  AppDb._(this.db);

  final Database db;

  static const int version = 1;

  static Future<AppDb> open({DatabaseFactory? factory, String? path, bool singleInstance = true}) async {
    final f = factory ?? databaseFactory;
    final dbPath = path ?? p.join(await f.getDatabasesPath(), 'hardware_bms.db');
    final db = await f.openDatabase(
      dbPath,
      options: OpenDatabaseOptions(
        version: version,
        singleInstance: singleInstance,
        onConfigure: (db) => db.execute('PRAGMA foreign_keys = ON'),
        onCreate: (db, v) => _create(db),
      ),
    );
    return AppDb._(db);
  }

  static Future<void> _create(Database db) async {
    final batch = db.batch();
    batch.execute('CREATE TABLE kv (key TEXT PRIMARY KEY, value TEXT)');
    batch.execute('''CREATE TABLE shops (
      id INTEGER PRIMARY KEY, name TEXT NOT NULL, code TEXT, location TEXT, phone TEXT, is_active INTEGER DEFAULT 1)''');
    batch.execute('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, is_active INTEGER DEFAULT 1)');
    batch.execute('CREATE TABLE expense_categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, is_active INTEGER DEFAULT 1)');
    batch.execute('''CREATE TABLE products (
      id INTEGER PRIMARY KEY, category_id INTEGER, code TEXT, name TEXT NOT NULL, unit TEXT,
      cost_price REAL DEFAULT 0, selling_price REAL DEFAULT 0, reorder_level REAL DEFAULT 0, is_active INTEGER DEFAULT 1)''');
    // Parties: uid is the local_uuid for records created on this device, or "srv:<id>".
    for (final t in ['customers', 'suppliers']) {
      batch.execute('''CREATE TABLE $t (
        uid TEXT PRIMARY KEY, server_id INTEGER UNIQUE, name TEXT NOT NULL, phone TEXT, is_active INTEGER DEFAULT 1)''');
    }
    batch.execute('''CREATE TABLE stock (
      shop_id INTEGER NOT NULL, product_id INTEGER NOT NULL, server_qty REAL DEFAULT 0,
      PRIMARY KEY (shop_id, product_id))''');
    // Stock changes made locally that the server has not accepted yet.
    batch.execute('''CREATE TABLE stock_deltas (
      id INTEGER PRIMARY KEY AUTOINCREMENT, txn_uuid TEXT NOT NULL, shop_id INTEGER NOT NULL,
      product_id INTEGER NOT NULL, delta REAL NOT NULL)''');
    batch.execute('CREATE INDEX stock_deltas_txn ON stock_deltas (txn_uuid)');
    batch.execute('''CREATE TABLE debts (
      uid TEXT PRIMARY KEY, server_id INTEGER UNIQUE, shop_id INTEGER, type TEXT, party_name TEXT, party_phone TEXT,
      original_amount REAL, paid_amount REAL, balance REAL, debt_date TEXT, due_date TEXT, status TEXT,
      provisional INTEGER DEFAULT 0, source_uuid TEXT)''');
    batch.execute('''CREATE TABLE daily_sessions (
      uid TEXT PRIMARY KEY, server_id INTEGER, shop_id INTEGER NOT NULL, business_date TEXT NOT NULL, status TEXT NOT NULL,
      opening_cash REAL DEFAULT 0, closing_cash REAL, expected_cash REAL, totals TEXT, local_uuid TEXT,
      UNIQUE (shop_id, business_date))''');
    batch.execute('''CREATE TABLE sync_queue (
      seq INTEGER PRIMARY KEY AUTOINCREMENT, local_uuid TEXT NOT NULL, entity TEXT NOT NULL, payload TEXT NOT NULL,
      user_id INTEGER, shop_id INTEGER, business_date TEXT, summary TEXT, amount REAL DEFAULT 0, method TEXT,
      status TEXT NOT NULL DEFAULT 'pending', retries INTEGER DEFAULT 0, error TEXT, server_id INTEGER, reference TEXT,
      created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE (local_uuid, entity))''');
    batch.execute('CREATE INDEX sync_queue_status ON sync_queue (status)');
    batch.execute('CREATE TABLE notifications (id INTEGER PRIMARY KEY, type TEXT, title TEXT, message TEXT, created_at TEXT)');
    await batch.commit(noResult: true);
  }

  Future<String?> getKv(String key) async {
    final rows = await db.query('kv', where: 'key = ?', whereArgs: [key]);
    return rows.isEmpty ? null : rows.first['value'] as String?;
  }

  Future<void> setKv(String key, String? value) async {
    if (value == null) {
      await db.delete('kv', where: 'key = ?', whereArgs: [key]);
    } else {
      await db.insert('kv', {'key': key, 'value': value}, conflictAlgorithm: ConflictAlgorithm.replace);
    }
  }

  Future<void> close() => db.close();
}
