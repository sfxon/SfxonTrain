import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import type { AnswerResult, FinishResult, StartResult } from './Types'

const url = (path: string): string => generateUrl(`/apps/sfxontrain${path}`)

/** Startet einen neuen Durchlauf oder liefert den offenen zum Fortsetzen. */
export async function startRun(trainUserId: number): Promise<StartResult> {
    const { data } = await axios.post<StartResult>(url(`/api/trainings/${trainUserId}/run`))
    return data
}

/** Merkt sich die aktuelle Seite im Run (bei jedem Seitenwechsel). */
export async function saveProgress(runId: number, pageId: string): Promise<void> {
    await axios.put(url(`/api/runs/${runId}/progress`), { pageId })
}

export async function submitAnswer(
    runId: number,
    questionId: string,
    selected: string[],
): Promise<AnswerResult> {
    const { data } = await axios.post<AnswerResult>(url(`/api/runs/${runId}/answers`), {
        questionId,
        selected,
    })
    return data
}

export async function finishRun(runId: number): Promise<FinishResult> {
    const { data } = await axios.post<FinishResult>(url(`/api/runs/${runId}/finish`))
    return data
}

/** Liest die Fehlermeldung des Servers, sonst den Fallback. */
export function errorMessage(error: unknown, fallback: string): string {
    const message = (error as { response?: { data?: { message?: unknown } } })?.response?.data?.message
    return typeof message === 'string' && message !== '' ? message : fallback
}