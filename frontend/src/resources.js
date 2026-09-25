// Description des ressources administrables : colonnes du tableau et champs du formulaire.
// `toForm` convertit un élément lu (relations imbriquées) vers les champs envoyés à l'API.
// `options` : ressource dont on charge la liste pour un <select> (valeur = id).

export const resources = {
  services: {
    title: 'Services',
    path: '/services',
    columns: [
      { key: 'libelle', label: 'Libellé' },
      { key: 'localisation', label: 'Localisation' },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'localisation', label: 'Localisation', max: 50 },
    ],
  },
  metiers: {
    title: 'Métiers',
    path: '/metiers',
    columns: [
      { key: 'libelle', label: 'Libellé' },
      { key: 'nom', label: 'Nom' },
      { key: 'prenom', label: 'Prénom' },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'nom', label: 'Nom', max: 50 },
      { key: 'prenom', label: 'Prénom', max: 50 },
    ],
  },
  'numeros-urgence': {
    title: "Numéros d'urgence",
    path: '/numeros-urgence',
    columns: [
      { key: 'libelle', label: 'Libellé' },
      { key: 'numero', label: 'Numéro' },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'numero', label: 'Numéro', max: 50 },
    ],
  },
  personnel: {
    title: 'Personnel de garde',
    path: '/personnel',
    paginated: true,
    columns: [
      { key: 'libelle', label: 'Libellé' },
      { key: 'service', label: 'Service', get: (r) => r.service?.libelle },
      { key: 'metier', label: 'Métier', get: (r) => r.metier?.libelle },
    ],
    fields: [
      { key: 'libelle', label: 'Libellé', max: 50 },
      { key: 'serviceId', label: 'Service', options: 'services', optionLabel: 'libelle' },
      { key: 'metierId', label: 'Métier', options: 'metiers', optionLabel: 'libelle' },
    ],
    toForm: (r) => ({ libelle: r.libelle, serviceId: r.service?.id, metierId: r.metier?.id }),
  },
  'numeros-garde': {
    title: 'Numéros de garde',
    path: '/numeros-garde',
    columns: [
      { key: 'numero', label: 'Numéro' },
      { key: 'type', label: 'Type' },
      { key: 'personnel', label: 'Personnel', get: (r) => r.personnelDeGarde?.libelle },
    ],
    fields: [
      { key: 'numero', label: 'Numéro', max: 50 },
      { key: 'type', label: 'Type', max: 50 },
      {
        key: 'personnelDeGardeId',
        label: 'Personnel',
        options: 'personnel',
        optionLabel: 'libelle',
        // /api/personnel est paginé (100 max par page) : on charge la première page.
        params: { limit: 100 },
      },
    ],
    toForm: (r) => ({ numero: r.numero, type: r.type, personnelDeGardeId: r.personnelDeGarde?.id }),
  },
}
