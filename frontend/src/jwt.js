// Identifiant lu dans la charge utile du JWT, pour l'affichage seulement (le token n'est pas vérifié ici : l'API le fait).
// Renvoie '' si le token est absent ou illisible.
export function tokenUsername(token) {
  try {
    const b64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')
    const bytes = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))
    const { username } = JSON.parse(new TextDecoder().decode(bytes))
    return typeof username === 'string' ? username : ''
  } catch {
    return ''
  }
}
