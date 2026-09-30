// Menu « Administration » de la barre latérale : regroupement des entrées et groupe de la page active (testé dans menu.test.js).

const GROUPS = [
  { id: 'personnel', label: 'Personnel', ids: ['personnel'] },
  { id: 'gardes', label: 'Gardes', ids: ['gardes', 'numeros-garde', 'numeros-urgence'] },
  { id: 'referentiels', label: 'Référentiels', ids: ['services', 'metiers'] },
  { id: 'suivi', label: 'Suivi', ids: ['traces'] },
]

// Ressources administrables -> [{ id, label, items: [{ id, label, to }] }] ; une entrée absente de GROUPS va dans « Autres », les groupes vides sont omis.
export function buildNavGroups(resources) {
  const entries = Object.entries(resources).map(([key, r]) => ({ id: key, label: r.title, to: { name: 'admin', params: { resource: key } } }))
  entries.push({ id: 'traces', label: 'Journal des actions', to: { name: 'traces' } })

  const known = new Set(GROUPS.flatMap((g) => g.ids))
  const groups = GROUPS.map((g) => ({ id: g.id, label: g.label, items: g.ids.map((id) => entries.find((e) => e.id === id)).filter(Boolean) }))
  groups.push({ id: 'autres', label: 'Autres', items: entries.filter((e) => !known.has(e.id)) })
  return groups.filter((g) => g.items.length)
}

// Groupe contenant la page affichée (route « admin » ou « traces »), sinon null
export function activeGroupId(groups, route) {
  const id = route.name === 'traces' ? 'traces' : route.name === 'admin' ? route.params.resource : null
  return groups.find((g) => g.items.some((i) => i.id === id))?.id ?? null
}
