import { describe, it, expect, vi, beforeEach } from 'vitest'

// Le store d'authentification est remplacé : on contrôle le jeton et on observe logout().
const auth = { token: null, logout: vi.fn() }
vi.mock('./stores/auth', () => ({ useAuth: () => auth }))

import { request, get, post, ApiError } from './api'

const response = (status, body = null, headers = {}) => ({
  status,
  ok: status >= 200 && status < 300,
  headers: { get: (h) => headers[h] ?? null },
  json: async () => body,
})

let fetchMock

beforeEach(() => {
  auth.token = null
  auth.logout.mockClear()
  fetchMock = vi.fn()
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('window', { location: { origin: 'http://localhost' } })
})

describe('request', () => {
  it("construit l'URL sous /api et ignore les paramètres vides", async () => {
    fetchMock.mockResolvedValue(response(200, []))
    await get('/personnel', { q: 'dupont', serviceId: '', metierId: null, page: 2 })

    const url = fetchMock.mock.calls[0][0]
    expect(url.pathname).toBe('/api/personnel')
    expect(url.searchParams.get('q')).toBe('dupont')
    expect(url.searchParams.get('page')).toBe('2')
    expect(url.searchParams.has('serviceId')).toBe(false)
    expect(url.searchParams.has('metierId')).toBe(false)
  })

  it('envoie le jeton JWT quand il existe, et rien sinon', async () => {
    fetchMock.mockResolvedValue(response(200, []))

    await get('/services')
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBeUndefined()

    auth.token = 'abc'
    await get('/services')
    expect(fetchMock.mock.calls[1][1].headers.Authorization).toBe('Bearer abc')
  })

  it('sérialise le corps JSON avec son Content-Type', async () => {
    fetchMock.mockResolvedValue(response(201, { id: 1 }))
    await post('/services', { libelle: 'Urgences' })

    const init = fetchMock.mock.calls[0][1]
    expect(init.method).toBe('POST')
    expect(init.headers['Content-Type']).toBe('application/json')
    expect(init.body).toBe('{"libelle":"Urgences"}')
  })

  it('renvoie les données et les en-têtes de pagination', async () => {
    fetchMock.mockResolvedValue(
      response(200, [{ id: 1 }], { 'X-Total-Count': '50', 'X-Page': '2', 'X-Per-Page': '20', 'X-Total-Pages': '3' }),
    )
    const res = await get('/personnel')

    expect(res).toEqual({ data: [{ id: 1 }], total: 50, page: 2, perPage: 20, totalPages: 3 })
  })

  it("renvoie null pour les en-têtes absents et n'échoue pas sur un 204", async () => {
    fetchMock.mockResolvedValue(response(204))
    const res = await request('DELETE', '/services/1')

    expect(res.data).toBeNull()
    expect(res.total).toBeNull()
    expect(res.totalPages).toBeNull()
  })
})

describe('erreurs', () => {
  it("préfère le message de l'API et transmet les erreurs de champs", async () => {
    fetchMock.mockResolvedValue(response(422, { message: 'Invalide', errors: { libelle: ['Vide'] } }))

    const err = await get('/services').catch((e) => e)
    expect(err).toBeInstanceOf(ApiError)
    expect(err.status).toBe(422)
    expect(err.message).toBe('Invalide')
    expect(err.errors).toEqual({ libelle: ['Vide'] })
  })

  it.each([
    [401, 'Identifiants invalides ou session expirée.'],
    [409, 'Cet élément est encore utilisé ailleurs.'],
    [429, 'Trop de tentatives, réessayez dans quelques minutes.'],
    [503, 'Annuaire LDAP indisponible.'],
    [500, 'Erreur 500'],
  ])('message par défaut pour le statut %i', async (status, message) => {
    fetchMock.mockResolvedValue(response(status, null))
    await expect(get('/services')).rejects.toMatchObject({ status, message })
  })

  it("signale une API injoignable (status 0)", async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    await expect(get('/services')).rejects.toMatchObject({ status: 0, message: "Impossible de joindre l'API." })
  })

  it('déconnecte sur un 401 seulement si un jeton existe', async () => {
    fetchMock.mockResolvedValue(response(401, null))

    await get('/x').catch(() => {})
    expect(auth.logout).not.toHaveBeenCalled()

    auth.token = 'expiré'
    await get('/x').catch(() => {})
    expect(auth.logout).toHaveBeenCalledTimes(1)
  })
})
