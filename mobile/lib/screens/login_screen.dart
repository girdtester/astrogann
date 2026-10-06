import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../core/api_exception.dart';
import '../theme/app_theme.dart';
import 'forgot_password_screen.dart';
import 'signup_screen.dart';

/// Login for users / traders (spec 3.2). Sends device_type=mobile and the FCM token when available.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _hide = true;
  String? _error;
  Map<String, List<String>> _fieldErrors = const {};

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
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
      await AppScope.of(context).auth.login(_email.text, _password.text);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _fieldErrors = e.fieldErrors;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AppScope.of(context);
    final reason = scope.auth.logoutReason;
    final appName = scope.settings.settings?.appName ?? 'ASTRO GANN';

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _form,
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Image.asset('assets/logo.png', width: 96, height: 96),
                  const SizedBox(height: 12),
                  Text(appName, textAlign: TextAlign.center,
                      style: const TextStyle(color: AppColors.oliveDark, fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: 1)),
                  const SizedBox(height: 4),
                  const Text('Log in to see the latest entries', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
                  const SizedBox(height: 24),
                  if (reason != null) ...[
                    _Notice(key: const Key('logout-reason'), text: reason, icon: Icons.info_outline_rounded),
                    const SizedBox(height: 16),
                  ],
                  if (_error != null) ...[
                    _Notice(key: const Key('login-error'), text: _error!, icon: Icons.warning_amber_rounded),
                    const SizedBox(height: 16),
                  ],
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        TextFormField(
                          key: const Key('email'),
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          autofillHints: const [AutofillHints.email],
                          textInputAction: TextInputAction.next,
                          decoration: InputDecoration(
                            labelText: 'Email',
                            prefixIcon: const Icon(Icons.mail_outline_rounded),
                            errorText: _fieldErrors['email']?.first,
                          ),
                          validator: (v) => v == null || !v.contains('@') ? 'Enter your email address' : null,
                        ),
                        const SizedBox(height: 14),
                        TextFormField(
                          key: const Key('password'),
                          controller: _password,
                          obscureText: _hide,
                          autofillHints: const [AutofillHints.password],
                          onFieldSubmitted: (_) => _busy ? null : _submit(),
                          decoration: InputDecoration(
                            labelText: 'Password',
                            prefixIcon: const Icon(Icons.lock_outline_rounded),
                            errorText: _fieldErrors['password']?.first,
                            suffixIcon: IconButton(
                              tooltip: _hide ? 'Show password' : 'Hide password',
                              icon: Icon(_hide ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                              onPressed: () => setState(() => _hide = !_hide),
                            ),
                          ),
                          validator: (v) => v == null || v.isEmpty ? 'Enter your password' : null,
                        ),
                        const SizedBox(height: 20),
                        FilledButton(
                          key: const Key('login-button'),
                          onPressed: _busy ? null : _submit,
                          child: _busy
                              ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                              : const Text('Log in'),
                        ),
                      ]),
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    key: const Key('forgot-link'),
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(
                      builder: (_) => ForgotPasswordScreen(initialEmail: _email.text),
                    )),
                    child: const Text('Forgot password?'),
                  ),
                  if (scope.settings.settings?.allowRegistration ?? true) ...[
                    const Divider(height: 24),
                    Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Text('New here?', style: TextStyle(color: AppColors.muted)),
                      TextButton(
                        key: const Key('signup-link'),
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SignupScreen())),
                        child: const Text('Create an account', style: TextStyle(fontWeight: FontWeight.w700)),
                      ),
                    ]),
                  ],
                  const SizedBox(height: 8),
                  const Text('One account can be logged in on one device at a time.',
                      textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted, fontSize: 12)),
                ]),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({super.key, required this.text, required this.icon});
  final String text;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.yellowLight,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: const Color(0xFFFDD847)),
        ),
        child: Row(children: [
          Icon(icon, color: AppColors.yellowDark),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: const TextStyle(color: AppColors.yellowDark))),
        ]),
      );
}
