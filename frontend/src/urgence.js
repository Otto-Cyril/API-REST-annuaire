// Pictogramme d'un numéro d'urgence, déduit de son libellé (sans accents ni majuscules). Icône neutre par défaut.
const RULES = [
  [/vital/, 'plus'],
  [/incendie|feu\b/, 'flame'],
  [/securite/, 'shield'],
  [/menage|collecte|dechet/, 'trash'],
  [/administrateur/, 'user'],
  [/cardio|cardiaque|perfusion/, 'heart'],
  [/chirurg|gastro|pneumo|infectio|urolog|thorac|medecin|docteur/, 'stethoscope'],
]

const normalize = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

export function urgenceIcon(libelle) {
  const text = normalize(libelle ?? '')
  const rule = RULES.find(([re]) => re.test(text))
  return rule ? rule[1] : 'phone'
}
