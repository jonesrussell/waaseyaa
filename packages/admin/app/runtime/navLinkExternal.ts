/** True when the link should open outside the Nuxt app (new tab / full navigation). */
export function adminNavLinkIsExternal(link: { href: string; external?: boolean }): boolean {
  if (link.external === true) {
    return true
  }
  const h = link.href.trim()
  return /^https?:\/\//i.test(h)
    || h.startsWith('//')
    || h.startsWith('mailto:')
    || h.startsWith('tel:')
}
