import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get } from '../api'

// Liste paginée avec recherche, filtres service/métier et tri, dont l'état vit dans l'URL
// (?q=&service=&metier=&sort=&page=) : lien partageable, retour arrière depuis une fiche.
// `path` : ressource de l'API (ex. '/personnel', '/personnes').
export function useDirectory(path) {
  const route = useRoute()
  const router = useRouter()

  const filters = reactive({
    q: String(route.query.q ?? ''),
    serviceId: String(route.query.service ?? ''),
    metierId: String(route.query.metier ?? ''),
    sort: route.query.sort === 'service' ? 'service' : 'nom', // tri, pas un filtre : exclu de hasFilters / resetFilters
  })
  const page = ref(Number(route.query.page) > 0 ? Number(route.query.page) : 1)
  const list = ref([])
  const meta = reactive({ total: 0, totalPages: 1 })
  const overall = ref(null) // total sans filtre
  const services = ref([])
  const metiers = ref([])
  const loading = ref(false)
  const error = ref('')

  let timer
  let seq = 0

  const hasFilters = computed(() => Boolean(filters.q || filters.serviceId || filters.metierId))
  const resetFilters = () => Object.assign(filters, { q: '', serviceId: '', metierId: '' })
  const serviceLabel = computed(() => services.value.find((s) => String(s.id) === filters.serviceId)?.libelle)
  const metierLabel = computed(() => metiers.value.find((m) => String(m.id) === filters.metierId)?.libelle)
  const countLabel = computed(() => `${meta.total} résultat${meta.total > 1 ? 's' : ''}`)

  function syncUrl() {
    const query = {}
    if (filters.q) query.q = filters.q
    if (filters.serviceId) query.service = filters.serviceId
    if (filters.metierId) query.metier = filters.metierId
    if (filters.sort !== 'nom') query.sort = filters.sort
    if (page.value > 1) query.page = String(page.value)
    router.replace({ query })
  }

  async function load() {
    const mine = ++seq
    loading.value = true
    error.value = ''
    syncUrl()
    try {
      const res = await get(path, { ...filters, page: page.value })
      if (mine !== seq) return // une requête plus récente a pris le relais
      list.value = res.data
      meta.total = res.total ?? res.data.length
      meta.totalPages = res.totalPages ?? 1
      if (!hasFilters.value) overall.value = meta.total
    } catch (e) {
      if (mine === seq) error.value = e.message
    } finally {
      if (mine === seq) loading.value = false
    }
  }

  // Toute modification de filtre revient à la page 1 ; la saisie est temporisée.
  watch(filters, () => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      page.value === 1 ? load() : (page.value = 1)
    }, 300)
  })
  watch(page, () => {
    load()
    window.scrollTo({ top: 0, behavior: 'smooth' })
  })

  onMounted(async () => {
    load()
    try {
      services.value = (await get('/services')).data
      metiers.value = (await get('/metiers')).data
    } catch {
      /* les filtres restent vides, la recherche texte fonctionne toujours */
    }
  })
  onBeforeUnmount(() => clearTimeout(timer))

  return { filters, page, list, meta, overall, services, metiers, loading, error, hasFilters, resetFilters, serviceLabel, metierLabel, countLabel, load }
}
