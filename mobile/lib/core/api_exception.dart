/// Error from the API envelope `{ success:false, message, errors: { code | field: [..] } }` (docs/API.md §1).
class ApiException implements Exception {
  ApiException(this.message, {this.code, this.status, this.fieldErrors = const {}});

  final String message;

  /// errors.code from the server, or 'network' / 'bad_response' for client-side problems.
  final String? code;
  final int? status;

  /// Validation errors (422): field -> messages.
  final Map<String, List<String>> fieldErrors;

  /// The account was logged in somewhere else, deactivated, or the token is no longer valid.
  bool get endsSession =>
      code == 'session_replaced' || code == 'account_inactive' || code == 'unauthenticated';

  bool get isNetwork => code == 'network';

  @override
  String toString() => message;
}
