import type { AuthUser } from './auth'
import client from './client'

export interface Trainer {
  id: number
  name: string
  email: string
}

export interface NewTrainer {
  name: string
  email: string
  password: string
}

export async function listTrainers(studioId: number): Promise<Trainer[]> {
  const { data } = await client.get<Trainer[]>(
    `/api/studios/${studioId}/trainers`,
  )
  return data
}

export async function registerTrainer(
  studioId: number,
  trainer: NewTrainer,
): Promise<AuthUser> {
  const { data } = await client.post<AuthUser>(
    `/api/studios/${studioId}/trainers`,
    trainer,
  )
  return data
}

export async function attachTrainer(
  studioId: number,
  email: string,
): Promise<AuthUser> {
  const { data } = await client.post<AuthUser>(
    `/api/studios/${studioId}/trainers/attach`,
    { email },
  )
  return data
}

export async function detachTrainer(
  studioId: number,
  trainerId: number,
): Promise<void> {
  await client.delete(`/api/studios/${studioId}/trainers/${trainerId}`)
}

export interface TrainerUpdate {
  name?: string
  email?: string
  password?: string
}

export async function updateTrainer(
  studioId: number,
  trainerId: number,
  update: TrainerUpdate,
): Promise<Trainer> {
  const { data } = await client.put<Trainer>(
    `/api/studios/${studioId}/trainers/${trainerId}`,
    update,
  )
  return data
}
