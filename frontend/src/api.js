import { useAuth } from './stores/auth'

const BASE = import.meta.env.VITE_API_URL ?? '/api'

export class ApiError extends Error {
  constructor(status, message, errors = null) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

const MESSAGES = {
  401: 'Identifiants invalides ou session expirée.',
  409: 'Cet élément est encore utilisé ailleurs.',
  429: 'Trop de tentatives, réessayez dans quelques minutes.',
  503: 'Annuaire LDAP indisponible.',
}

// Renvoie { data, total, page, perPage, totalPages } (les 4 derniers viennent des en-têtes de pagination).
export async function request(method, path, { body, params } = {}) {
  const auth = useAuth()
  const url = new URL(BASE + path, window.location.origin)
  for (const [k, v] of Object.entries(params ?? {})) {
    if (v !== '' && v != null) url.searchParams.set(k, v)
  }

  const headers = {}
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth.token) headers.Authorization = `Bearer ${auth.token}`

  let res
  try {
    res = await fetch(url, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined })
  } catch {
    throw new ApiError(0, "Impossible de joindre l'API.")
  }

  if (res.status === 401 && auth.token) auth.logout() // JWT expiré : retour à la connexion

  const payload = res.status === 204 ? null : await res.json().catch(() => null)
  if (!res.ok) {
    throw new ApiError(res.status, payload?.message ?? MESSAGES[res.status] ?? `Erreur ${res.status}`, payload?.errors)
  }

  const num = (h) => Number(res.headers.get(h)) || null
  return {
    data: payload,
    total: num('X-Total-Count'),
    page: num('X-Page'),
    perPage: num('X-Per-Page'),
    totalPages: num('X-Total-Pages'),
  }
}

export const get = (path, params) => request('GET', path, { params })
export const post = (path, body) => request('POST', path, { body })
export const put = (path, body) => request('PUT', path, { body })
export const del = (path) => request('DELETE', path)
