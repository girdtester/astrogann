import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../core/api_exception.dart';
import '../theme/app_theme.dart';

/// "Forgot password" (D-84): the server e-mails a reset link; the link opens the website reset page.
/// The new password then works in the app and on the website, and old logins are ended.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, this.initialEmail = ''});
  final String initialEmail;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _form = GlobalKey<FormState>();
  late final _email = TextEditingController(text: widget.initialEmail);
  bool _busy = false;
  String? _done;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await AppScope.of(context).auth.forgotPassword(_email.text);
      if (mounted) setState(() => _done = message);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.fieldErrors['email']?.first ?? e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Forgot password')),
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: _done != null
                    ? Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        const Icon(Icons.mark_email_read_outlined, size: 56, color: AppColors.olive),
                        const SizedBox(height: 16),
                        Text(_done!, key: const Key('forgot-done'), textAlign: TextAlign.center, style: const TextStyle(fontSize: 15)),
                        const SizedBox(height: 8),
                        const Text('Open the link in the e-mail, choose a new password, then log in here with it.',
                            textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
                        const SizedBox(height: 24),
                        FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Back to log in')),
                      ])
                    : Form(
                        key: _form,
                        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                          const Text('Enter the e-mail of your account. We will send you a link to choose a new password.',
                              style: TextStyle(color: AppColors.muted)),
                          const SizedBox(height: 16),
                          TextFormField(
                            key: const Key('forgot-email'),
                            controller: _email,
                            keyboardType: TextInputType.emailAddress,
                            decoration: InputDecoration(labelText: 'Email', prefixIcon: const Icon(Icons.mail_outline_rounded), errorText: _error),
                            validator: (v) => v == null || !v.contains('@') ? 'Enter your email address' : null,
                            onFieldSubmitted: (_) => _busy ? null : _submit(),
                          ),
                          const SizedBox(height: 20),
                          FilledButton(
                            key: const Key('forgot-button'),
                            onPressed: _busy ? null : _submit,
                            child: _busy
                                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                                : const Text('Send reset link'),
                          ),
                        ]),
                      ),
              ),
            ),
          ),
        ),
      );
}
