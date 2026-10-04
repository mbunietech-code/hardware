import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/api_client.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../l10n/l10n.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _url;
  late final TextEditingController _login;
  final _password = TextEditingController();
  bool _busy = false;
  bool _showServer = false;
  bool _hidePassword = true;

  @override
  void initState() {
    super.initState();
    final s = context.read<AppState>();
    _url = TextEditingController(text: s.serverUrl);
    _login = TextEditingController(text: (s.user?['email'] ?? s.user?['phone'] ?? '') as String);
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await context.read<AppState>().login(_url.text, _login.text, _password.text);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.isNetwork ? tr('Cannot reach the server. Check the internet and server address.') : e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = context.watch<AppState>();
    return Scaffold(
      body: SingleChildScrollView(
        child: Column(
          children: [
            PhotoHeader(
              image: Photos.shop,
              radius: 36,
              creditOnTop: true,
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 12, 24, 70),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Align(alignment: Alignment.centerRight, child: LanguageSwitch()),
                      const SizedBox(height: 18),
                      Container(
                        width: 58,
                        height: 58,
                        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .15), borderRadius: BorderRadius.circular(18), border: Border.all(color: Colors.white24)),
                        child: const Icon(Icons.storefront_rounded, size: 32, color: Colors.white),
                      ),
                      const SizedBox(height: 18),
                      Text(tr('Hardware BMS'), style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 6),
                      Text(tr('Shop operations · works offline'), style: TextStyle(color: Colors.white.withValues(alpha: .75), fontSize: 14)),
                    ],
                  ),
                ),
              ),
            ),
            Transform.translate(
              offset: const Offset(0, -44),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 440),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 18),
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(22),
                        child: Form(
                          key: _form,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text('${tr('Welcome back')} 👋', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
                              const SizedBox(height: 18),
                              if (s.sessionExpired && s.unsynced > 0) ...[
                                Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(color: Colors.amber.shade50, borderRadius: BorderRadius.circular(14)),
                                  child: Row(
                                    children: [
                                      Icon(Icons.cloud_upload_rounded, color: Colors.amber.shade800),
                                      const SizedBox(width: 10),
                                      Expanded(child: Text(tr('{n} record(s) are saved on this phone and will sync after you log in.', {'n': s.unsynced}))),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 14),
                              ],
                              TextFormField(
                                controller: _login,
                                decoration: InputDecoration(labelText: tr('Email or phone'), prefixIcon: const Icon(Icons.person_outline_rounded)),
                                keyboardType: TextInputType.emailAddress,
                                validator: (v) => (v ?? '').trim().isEmpty ? tr('Required') : null,
                              ),
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _password,
                                obscureText: _hidePassword,
                                decoration: InputDecoration(
                                  labelText: tr('Password'),
                                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                                  suffixIcon: IconButton(
                                    icon: Icon(_hidePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                    onPressed: () => setState(() => _hidePassword = !_hidePassword),
                                  ),
                                ),
                                validator: (v) => (v ?? '').isEmpty ? tr('Required') : null,
                                onFieldSubmitted: (_) => _submit(),
                              ),
                              const SizedBox(height: 6),
                              Align(
                                alignment: Alignment.centerLeft,
                                child: TextButton.icon(
                                  onPressed: () => setState(() => _showServer = !_showServer),
                                  icon: const Icon(Icons.dns_outlined, size: 18),
                                  label: Text(tr('Server: {url}', {'url': _url.text}), overflow: TextOverflow.ellipsis),
                                ),
                              ),
                              if (_showServer) ...[
                                TextFormField(
                                  controller: _url,
                                  decoration: InputDecoration(labelText: tr('Server address'), hintText: 'https://shop.example.com'),
                                  keyboardType: TextInputType.url,
                                  onChanged: (_) => setState(() {}),
                                  validator: (v) => Uri.tryParse(v ?? '')?.hasScheme == true ? null : tr('Enter a full address, e.g. http://192.168.1.10:8765'),
                                ),
                                const SizedBox(height: 8),
                              ],
                              const SizedBox(height: 10),
                              FilledButton(
                                onPressed: _busy ? null : _submit,
                                style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 17)),
                                child: _busy
                                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                    : Text(tr('Log in')),
                              ),
                              const SizedBox(height: 14),
                              Row(
                                children: [
                                  const Icon(Icons.wifi_off_rounded, size: 16, color: Brand.muted),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      tr('The first login needs internet. After that you can work offline.'),
                                      style: const TextStyle(color: Brand.muted, fontSize: 12.5),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
