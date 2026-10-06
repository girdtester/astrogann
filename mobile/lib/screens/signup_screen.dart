import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../core/api_exception.dart';
import '../theme/app_theme.dart';

/// Self sign-up (D-85). New accounts have no subscription: latest few entries + "Show More" until the admin
/// activates a subscription.
class SignupScreen extends StatefulWidget {
  const SignupScreen({super.key});

  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;
  bool _hide = true;
  String? _error;
  Map<String, List<String>> _fieldErrors = const {};

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _password, _confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = const {};
    });
    try {
      await AppScope.of(context).auth.register(name: _name.text, email: _email.text, phone: _phone.text, password: _password.text);
      if (mounted) Navigator.of(context).popUntil((r) => r.isFirst); // auth state switches to the home screen
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.fieldErrors.isEmpty ? e.message : null;
          _fieldErrors = e.fieldErrors;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String? _err(String field) => _fieldErrors[field]?.first;

  @override
  Widget build(BuildContext context) {
    final limit = AppScope.of(context).settings.settings?.entriesLimitExpired ?? 3;
    return Scaffold(
      appBar: AppBar(title: const Text('Create account')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _form,
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text('Free sign-up. You will see the latest $limit entries in each category right away. '
                      'Contact the admin to subscribe for full access.',
                      style: const TextStyle(color: AppColors.muted)),
                  const SizedBox(height: 16),
                  if (_error != null) ...[
                    Text(_error!, style: const TextStyle(color: AppColors.yellowDark, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 12),
                  ],
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        TextFormField(
                          key: const Key('signup-name'),
                          controller: _name,
                          textCapitalization: TextCapitalization.words,
                          decoration: InputDecoration(labelText: 'Full name', prefixIcon: const Icon(Icons.person_outline), errorText: _err('name')),
                          validator: (v) => (v ?? '').trim().isEmpty ? 'Enter your name' : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          key: const Key('signup-email'),
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          decoration: InputDecoration(labelText: 'Email', prefixIcon: const Icon(Icons.mail_outline_rounded), errorText: _err('email')),
                          validator: (v) => v == null || !v.contains('@') ? 'Enter a valid email' : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          key: const Key('signup-phone'),
                          controller: _phone,
                          keyboardType: TextInputType.phone,
                          decoration: InputDecoration(
                            labelText: 'Mobile number (optional)',
                            prefixIcon: const Icon(Icons.phone_outlined),
                            helperText: 'So the admin can reach you about your subscription.',
                            errorText: _err('phone'),
                          ),
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          key: const Key('signup-password'),
                          controller: _password,
                          obscureText: _hide,
                          decoration: InputDecoration(
                            labelText: 'Password',
                            prefixIcon: const Icon(Icons.lock_outline_rounded),
                            helperText: 'At least 8 characters',
                            errorText: _err('password'),
                            suffixIcon: IconButton(
                              icon: Icon(_hide ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                              onPressed: () => setState(() => _hide = !_hide),
                            ),
                          ),
                          validator: (v) => (v ?? '').length < 8 ? 'At least 8 characters' : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          key: const Key('signup-confirm'),
                          controller: _confirm,
                          obscureText: _hide,
                          decoration: const InputDecoration(labelText: 'Confirm password', prefixIcon: Icon(Icons.lock_outline_rounded)),
                          validator: (v) => v != _password.text ? 'Passwords do not match' : null,
                        ),
                        const SizedBox(height: 20),
                        FilledButton(
                          key: const Key('signup-button'),
                          onPressed: _busy ? null : _submit,
                          child: _busy
                              ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                              : const Text('Sign up'),
                        ),
                      ]),
                    ),
                  ),
                ]),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
