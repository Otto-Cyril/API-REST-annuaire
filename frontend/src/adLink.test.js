import { describe, it, expect } from 'vitest'
import { adEditUrl } from './adLink'

describe('adEditUrl', () => {
  it("remplace {username} par l'identifiant encodé", () => {
    expect(adEditUrl('jdupont', 'https://ad.exemple.fr/users/{username}/edit')).toBe('https://ad.exemple.fr/users/jdupont/edit')
    expect(adEditUrl('a b', 'https://x/?u={username}')).toBe('https://x/?u=a%20b')
  })

  it("n'affiche rien sans adresse configurée ou sans identifiant", () => {
    expect(adEditUrl('jdupont', '')).toBeNull()
    expect(adEditUrl('jdupont', undefined)).toBeNull()
    expect(adEditUrl('', 'https://x/{username}')).toBeNull()
  })
})
