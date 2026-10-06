import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../data/api_client.dart';
import '../l10n/l10n.dart';
import '../state/app_state.dart';
import '../widgets/common.dart';

/// Change password. When [forced] (default or temporary password) the user cannot skip it.
class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key, this.forced = false});
  final bool forced;

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _new = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    final s = context.read<AppState>();
    try {
      await s.changePassword(_current.text, _new.text);
      if (!mounted) return;
      showMessage(context, tr('Password changed.'));
      if (!widget.forced) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.isNetwork ? tr('Changing the password needs internet.') : e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(tr('Change password')),
        automaticallyImplyLeading: !widget.forced,
        actions: [if (widget.forced) TextButton(onPressed: () => context.read<AppState>().logout(), child: Text(tr('Log out')))],
      ),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (widget.forced)
              Card(
                color: Colors.amber.shade50,
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Row(children: [
                    Icon(Icons.lock_reset_rounded, color: Colors.amber.shade800),
                    const SizedBox(width: 12),
                    Expanded(child: Text(tr('Please choose your own password before continuing.'))),
                  ]),
                ),
              ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _current,
              obscureText: true,
              decoration: InputDecoration(labelText: tr('Current password'), prefixIcon: const Icon(Icons.lock_outline_rounded)),
              validator: (v) => (v ?? '').isEmpty ? tr('Required') : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _new,
              obscureText: true,
              decoration: InputDecoration(labelText: tr('New password'), helperText: tr('At least 8 characters.'), prefixIcon: const Icon(Icons.password_rounded)),
              validator: (v) => (v ?? '').length < 8
                  ? tr('At least 8 characters.')
                  : v == _current.text
                      ? tr('Choose a password different from the current one.')
                      : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _confirm,
              obscureText: true,
              decoration: InputDecoration(labelText: tr('Confirm new password'), prefixIcon: const Icon(Icons.password_rounded)),
              validator: (v) => v != _new.text ? tr('Passwords do not match.') : null,
            ),
            const SizedBox(height: 22),
            FilledButton(
              onPressed: _busy ? null : _save,
              child: _busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text(tr('Save new password')),
            ),
          ],
        ),
      ),
    );
  }
}
