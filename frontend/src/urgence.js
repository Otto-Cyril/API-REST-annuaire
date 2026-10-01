// Pictogramme d'un numéro d'urgence, déduit de son libellé (sans accents ni majuscules). Icône neutre par défaut.
const RULES = [
  [/vital/, 'plus'],
  [/samu|smur|ambulance|reanimation/, 'ambulance'],
  [/incendie|pompier|sapeur|feu\b/, 'flame'],
  [/securite|police|gendarmerie|vigile|gardien/, 'shield'],
  [/menage|collecte|dechet/, 'trash'],
  [/administrateur|direction|astreinte|cadre/, 'user'],
  [/cardio|cardiaque|perfusion/, 'heart'],
  [/pharmaci/, 'pill'],
  [/laborato|biolog|\bsang\b/, 'drop'],
  [/technique|maintenance|electricite|ascenseur/, 'wrench'],
  [/informatique|\bdsi\b/, 'computer'],
  [/covid|infectio|hygiene/, 'virus'],
  [/maternite|naissance|neonat|pediatr|bebe/, 'baby'],
  [/\blits?\b|hospitalisation|admission|entrees/, 'bed'],
  [/chirurg|gastro|pneumo|urolog|thorac|medecin|docteur|psychiatr|psycholog|anesthesi|radiolog|imagerie|\bbloc\b/, 'stethoscope'],
]

const normalize = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

export function urgenceIcon(libelle) {
  const text = normalize(libelle ?? '')
  const rule = RULES.find(([re]) => re.test(text))
  return rule ? rule[1] : 'phone'
}

// Pictogrammes proposés à l'administrateur (liste déroulante). Doit rester alignée avec NumeroUrgence::ICONES côté API.
export const URGENCE_ICONS = [
  { value: 'phone', label: 'Téléphone' },
  { value: 'plus', label: 'Urgence vitale' },
  { value: 'flame', label: 'Incendie' },
  { value: 'shield', label: 'Sécurité' },
  { value: 'trash', label: 'Déchets, ménage' },
  { value: 'user', label: 'Personne, administration' },
  { value: 'heart', label: 'Cardiologie' },
  { value: 'stethoscope', label: 'Médecin, spécialiste' },
  { value: 'alert', label: 'Alerte' },
  { value: 'bed', label: 'Hospitalisation, lits' },
  { value: 'pill', label: 'Pharmacie' },
  { value: 'drop', label: 'Sang, laboratoire' },
  { value: 'building', label: 'Bâtiment' },
  { value: 'wrench', label: 'Technique, maintenance' },
  { value: 'ambulance', label: 'Ambulance, SAMU' },
  { value: 'computer', label: 'Informatique' },
  { value: 'virus', label: 'Infectiologie, hygiène' },
  { value: 'baby', label: 'Maternité, pédiatrie' },
]

// Icône choisie par l'administrateur, sinon déduite du libellé.
export const iconOf = (n) => n.icone || urgenceIcon(n.libelle)
