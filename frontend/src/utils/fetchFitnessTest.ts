import api from '../lib/api'

export interface FitnessTestData {
  service: string
  status: string
  message: string
  timestamp: string
}

export default async function fetchFitnessTest(): Promise<FitnessTestData> {
  const { data } = await api.get<FitnessTestData>('/fitness-test')
  return data
}
