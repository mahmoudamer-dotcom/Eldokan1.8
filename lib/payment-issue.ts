/** Backend 0.8.0 returns a code string; newer SDK contracts describe an Issue. */
export function paymentIssueCode(issue: unknown): string | null {
  const code = typeof issue === 'string' ? issue
    : issue && typeof issue === 'object' && 'code' in issue ? issue.code : null
  return typeof code === 'string' && /^[a-z][a-z0-9_]{0,99}$/.test(code) ? code : null
}
