// Fonctions pures des listes de résultats (testées dans list.test.js).

// Minuscules sans accents, en gardant la même longueur que le texte d'origine (les indices restent valables).
const fold = (s) =>
  s
    .split('')
    .map((c) => {
      const base = c.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
      return base.length === 1 ? base : c.toLowerCase()
    })
    .join('')

// Découpe `text` en [{ text, match }] pour mettre en évidence les occurrences de `query` (sans tenir compte de la casse ni des accents).
export function splitMatch(text, query) {
  const needle = fold((query ?? '').trim())
  if (!needle) return [{ text, match: false }]
  const hay = fold(text)
  const parts = []
  let from = 0
  for (let i = hay.indexOf(needle); i !== -1; i = hay.indexOf(needle, from)) {
    if (i > from) parts.push({ text: text.slice(from, i), match: false })
    parts.push({ text: text.slice(i, i + needle.length), match: true })
    from = i + needle.length
  }
  if (from < text.length) parts.push({ text: text.slice(from), match: false })
  return parts.length ? parts : [{ text, match: false }]
}

// Insère une ligne d'en-tête { header: true, id, label } avant chaque changement de service dans une liste déjà triée par service.
// `serviceOf` renvoie { id, libelle } pour un élément.
export function withServiceHeaders(items, serviceOf) {
  const rows = []
  let current
  for (const item of items) {
    const s = serviceOf(item)
    if (s.id !== current) {
      rows.push({ header: true, id: `h-${s.id}`, label: s.libelle })
      current = s.id
    }
    rows.push(item)
  }
  return rows
}
