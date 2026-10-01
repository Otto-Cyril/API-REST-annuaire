// Menu « Administration » de la barre latérale : regroupement des entrées et groupe de la page active (testé dans menu.test.js).

const GROUPS = [
  { id: 'gardes', label: 'Gardes', ids: ['personnel', 'gardes', 'numeros-garde'] },
  { id: 'urgence', label: 'Urgences', ids: ['numeros-urgence'] },
  { id: 'suivi', label: 'Suivi', ids: ['dashboard', 'traces'] },
]

// Ressources administrables -> [{ id, label, items: [{ id, label, to }] }] ; une entrée absente de GROUPS va dans « Autres », les groupes vides sont omis.
export function buildNavGroups(resources) {
  const entries = Object.entries(resources).map(([key, r]) => ({ id: key, label: r.title, to: { name: 'admin', params: { resource: key } } }))
  entries.push({ id: 'dashboard', label: 'Tableau de bord', to: { name: 'dashboard' } })
  entries.push({ id: 'traces', label: 'Journal des actions', to: { name: 'traces' } })

  const known = new Set(GROUPS.flatMap((g) => g.ids))
  const groups = GROUPS.map((g) => ({ id: g.id, label: g.label, items: g.ids.map((id) => entries.find((e) => e.id === id)).filter(Boolean) }))
  groups.push({ id: 'autres', label: 'Autres', items: entries.filter((e) => !known.has(e.id)) })
  return groups.filter((g) => g.items.length)
}

// Groupe contenant la page affichée (route « admin », « dashboard » ou « traces »), sinon null
export function activeGroupId(groups, route) {
  const id = route.name === 'traces' || route.name === 'dashboard' ? route.name : route.name === 'admin' ? route.params.resource : null
  return groups.find((g) => g.items.some((i) => i.id === id))?.id ?? null
}
