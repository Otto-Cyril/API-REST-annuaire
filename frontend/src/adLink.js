// Lien « Modifier dans l'AD » : l'annuaire est lu dans l'AD, on ne le modifie pas ici.
// VITE_AD_EDIT_URL (frontend/.env.local) : adresse de l'outil d'administration de l'AD, avec {username} à la place de l'identifiant.
// Sans cette variable, aucun bouton n'est affiché.
export function adEditUrl(username, template = import.meta.env.VITE_AD_EDIT_URL) {
  if (!template || !username) return null
  return String(template).replace('{username}', encodeURIComponent(username))
}
