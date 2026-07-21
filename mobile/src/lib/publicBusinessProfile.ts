import axios from 'axios'
import { BACKEND_URL } from './pairing'

export interface PublicBusinessProfile {
  business_name: string
  business_address: string
  location_landmarks: string
  contact_email: string | null
  contact_phone: string | null
  facebook_url: string
  messenger_url: string
  map_url: string
  addons: Array<[string, string]>
}

export const defaultPublicBusinessProfile: PublicBusinessProfile = {
  business_name: 'Casa Paraiso Body and Wellness Spa',
  business_address: 'Barangay Cuta East, Santa Teresita, Batangas, Philippines',
  location_landmarks: 'In front of Alfamart and PLDT; in the same building as BDO Network Bank.',
  contact_email: null,
  contact_phone: null,
  facebook_url: 'https://www.facebook.com/61579320037378',
  messenger_url: 'https://m.me/61579320037378',
  map_url: 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso+Body+%26+Wellness+Spa%2C+Cuta+East%2C+Santa+Teresita%2C+Batangas',
  addons: [],
}

export async function loadPublicBusinessProfile(): Promise<PublicBusinessProfile> {
  try {
    const response = await axios.get<{ data: Partial<PublicBusinessProfile> }>(BACKEND_URL + '/api/v1/public/business-profile', {
      headers: { Accept: 'application/json' },
      timeout: 15_000,
      withCredentials: false,
    })

    return { ...defaultPublicBusinessProfile, ...response.data.data }
  } catch {
    return defaultPublicBusinessProfile
  }
}
