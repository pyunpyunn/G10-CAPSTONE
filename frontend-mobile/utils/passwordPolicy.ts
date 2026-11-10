export function passwordPolicyError(password: string): string | null {
  if (password.length > 128) return 'Password is too long.';
  if (password.length < 8 || !/[A-Z]/.test(password) || !/[0-9]/.test(password) || !/[^A-Za-z0-9\s]/.test(password)) {
    return 'Password must be at least 8 characters and contain an uppercase letter, a number, and a symbol.';
  }

  return null;
}
