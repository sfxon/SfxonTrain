<script setup lang="ts">

import { onBeforeUnmount, onMounted, ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { mdiFileDelimited, mdiFolderZip } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import SfxonTable from '@/components/SfxonTable'
import { ZipStoreWriter } from '@/utils/zipStore'

interface ParticipantRow {
    id: number,
    userId: string,
    displayName: string,
    status: 'open' | 'overdue' | 'completed',
    dueDate: string | null,
    completedAt: string | null,
    validUntil: string | null,
    scorePercent: number | null,
    runId: number | null,
}

interface AdminTrainParticipantsState {
    train: { id: number, title: string | null, versionNumber: number | null },
    rows: ParticipantRow[],
    stats: { open: number, overdue: number, completed: number, total: number },
    certificateCount: number,
    listState: {
        orderBy: string,
        orderDirection: 'ASC' | 'DESC',
    },
}

const APP_ID = 'sfxontrain'

const state = loadState<AdminTrainParticipantsState>(APP_ID, 'adminTrainParticipants')

const STATUS_LABELS: Record<ParticipantRow['status'], string> = {
    open: 'Offen',
    overdue: 'Überfällig',
    completed: 'Absolviert',
}

const columns = [
    { key: 'displayName', label: 'Name', sortable: true },
    { key: 'userId', label: 'Benutzer-ID', sortable: true },
    { key: 'status', label: 'Status', sortable: true, type: 'relatedEntity', relatedEntityName: 'status' },
    { key: 'dueDate', label: 'Fällig am', sortable: true, type: 'date' },
    { key: 'completedAt', label: 'Abgeschlossen am', sortable: true, type: 'date' },
    { key: 'validUntil', label: 'Gültig bis', sortable: true, type: 'date' },
    { key: 'scorePercent', label: 'Ergebnis (%)', sortable: true },
]

// Status wird über den vorhandenen Spaltentyp "relatedEntity" angezeigt (wie "active" in AdminTrain.vue).
const relatedEntityData = {
    status: Object.entries(STATUS_LABELS).map(([id, label]) => ({ id, label })),
}

/** Baut eine URL der aktuellen Seite mit geänderten Query-Parametern. */
function buildUrl(params: Record<string, string | number>): string {
    const url = new URL(window.location.href)
    for (const [key, value] of Object.entries(params)) {
        url.searchParams.set(key, String(value))
    }
    return url.toString()
}

function orderByCallback(key: string) {
    const sameColumn = state.listState.orderBy === key
    const direction = sameColumn && state.listState.orderDirection === 'ASC' ? 'DESC' : 'ASC'

    window.location.assign(buildUrl({ orderBy: key, orderDirection: direction }))
}

function onBackToTrainings() {
    window.location.assign(generateUrl('/apps/sfxontrain/admin/train/'))
}

// --- Zertifikat-Export: Die Seite holt die PDFs nacheinander und baut das ZIP im Browser ---

const isExporting = ref(false)
const exportProgress = ref({ done: 0, total: 0 })
const exportFailed = ref<string[]>([])
let abortController: AbortController | null = null

function certificateUrl(runId: number): string {
    return generateUrl('/apps/sfxontrain/api/admin/train/{trainId}/runs/{runId}/certificate', {
        trainId: state.train.id,
        runId,
    })
}

/** Holt ein einzelnes Zertifikat; ein Fehlversuch wird einmal wiederholt. */
async function fetchCertificate(runId: number, signal: AbortSignal): Promise<Blob> {
    let lastError: unknown

    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const response = await fetch(certificateUrl(runId), { credentials: 'same-origin', signal })
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`)
            }
            return await response.blob()
        } catch (error) {
            if (signal.aborted) {
                throw error
            }
            lastError = error
        }
    }

    throw lastError
}

function sanitizeFilePart(value: string): string {
    return value.replace(/[^\p{L}\p{N}._-]+/gu, '_').replace(/^_+|_+$/g, '')
}

function uniqueZipName(row: ParticipantRow, used: Set<string>): string {
    const name = sanitizeFilePart(row.displayName)
    const user = sanitizeFilePart(row.userId) || 'user'
    const base = name !== '' && name !== user ? `${name}_${user}` : user

    let candidate = `${base}.pdf`
    for (let i = 2; used.has(candidate); i++) {
        candidate = `${base}_${i}.pdf`
    }
    used.add(candidate)

    return candidate
}

async function onExportCertificates() {
    const targets = state.rows.filter((row) => row.runId !== null)
    if (targets.length === 0 || isExporting.value) {
        return
    }

    isExporting.value = true
    exportFailed.value = []
    exportProgress.value = { done: 0, total: targets.length }
    abortController = new AbortController()
    const { signal } = abortController

    const zip = new ZipStoreWriter()
    const usedNames = new Set<string>()
    let added = 0

    try {
        // Bewusst nacheinander: Jedes PDF wird serverseitig einzeln gerendert,
        // so bleibt die Last pro Request klein und der Fortschritt ist sichtbar.
        for (const row of targets) {
            if (signal.aborted) {
                break
            }

            try {
                const pdf = await fetchCertificate(row.runId as number, signal)
                await zip.addFile(uniqueZipName(row, usedNames), pdf)
                added++
            } catch {
                if (signal.aborted) {
                    break
                }
                exportFailed.value.push(row.displayName)
            }

            exportProgress.value.done++
        }

        if (!signal.aborted && added > 0) {
            downloadBlob(zip.finish(), `Zertifikate_${state.train.id}_${new Date().toISOString().slice(0, 10)}.zip`)
        }
    } finally {
        isExporting.value = false
        abortController = null
    }
}

function onCancelExport() {
    abortController?.abort()
}

function onBeforeUnload(event: BeforeUnloadEvent) {
    if (isExporting.value) {
        event.preventDefault()
        event.returnValue = ''
    }
}

onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload)
    abortController?.abort()
})

function downloadBlob(blob: Blob, filename: string) {
    const url = URL.createObjectURL(blob)

    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()

    // Verzögert freigeben, damit der Browser den Download sicher gestartet hat.
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
}

// --- CSV-Export (direkt im Browser, in der aktuell angezeigten Sortierung) ---

const CSV_SEPARATOR = ';' // Semikolon: wird von deutschem Excel direkt als Spaltentrenner erkannt.

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('de-DE') : ''
}

function csvCell(value: unknown): string {
    let text = value == null ? '' : String(value)

    // Schutz vor Formel-Injection in Excel & Co.
    if (/^[=+\-@\t\r]/.test(text)) {
        text = `'${text}`
    }

    return `"${text.replace(/"/g, '""')}"`
}

function onExportCsv() {
    const header = ['Name', 'Benutzer-ID', 'Status', 'Fällig am', 'Abgeschlossen am', 'Gültig bis', 'Ergebnis (%)']

    const lines = [
        header,
        ...state.rows.map((row) => [
            row.displayName,
            row.userId,
            STATUS_LABELS[row.status] ?? row.status,
            formatDate(row.dueDate),
            formatDate(row.completedAt),
            formatDate(row.validUntil),
            row.scorePercent ?? '',
        ]),
    ].map((cells) => cells.map(csvCell).join(CSV_SEPARATOR))

    // BOM, damit Excel UTF-8 (Umlaute) korrekt öffnet.
    const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' })
    downloadBlob(blob, `Teilnehmer_${state.train.id}_${new Date().toISOString().slice(0, 10)}.csv`)
}

</script>
<template>
    <NcContent :app-name="APP_ID">
        <NcAppContent :class="$style.content">
            <div :class="$style.page">
                <div :class="$style.topBar">
                    <NcButton variant="secondary" @click="onBackToTrainings()">
                        Zurück zu Trainings
                    </NcButton>
                </div>

                <h1 :class="$style.pageTitle">
                    Teilnehmer: {{ state.train.title ?? `Training #${state.train.id}` }}
                </h1>

                <p :class="$style.stats">
                    {{ state.stats.total }} Teilnehmer ·
                    {{ state.stats.open }} offen ·
                    {{ state.stats.overdue }} überfällig ·
                    {{ state.stats.completed }} absolviert
                </p>

                <div :class="$style.actions">
                    <NcButton :disabled="state.rows.length === 0" @click="onExportCsv()">
                        <template #icon>
                            <NcIconSvgWrapper :path="mdiFileDelimited" :size="20" />
                        </template>
                        CSV exportieren
                    </NcButton>
                    <NcButton
                        :disabled="state.certificateCount === 0 || isExporting"
                        @click="onExportCertificates()">
                        <template #icon>
                            <NcIconSvgWrapper :path="mdiFolderZip" :size="20" />
                        </template>
                        {{ isExporting
                            ? `Zertifikate werden erzeugt … ${exportProgress.done} / ${exportProgress.total}`
                            : `Alle Zertifikate exportieren (${state.certificateCount})` }}
                    </NcButton>
                    <NcButton v-if="isExporting" variant="secondary" @click="onCancelExport()">
                        Abbrechen
                    </NcButton>
                </div>

                <progress
                    v-if="isExporting"
                    :class="$style.progress"
                    :value="exportProgress.done"
                    :max="exportProgress.total"
                />

                <NcNoteCard v-if="!isExporting && exportFailed.length > 0" type="warning">
                    Für {{ exportFailed.length }} Teilnehmer konnte kein Zertifikat erzeugt werden und es fehlt im ZIP:
                    {{ exportFailed.join(', ') }}
                </NcNoteCard>

                <p v-if="state.rows.length === 0" :class="$style.empty">
                    Diesem Training sind noch keine Benutzer zugewiesen.
                </p>

                <SfxonTable
                    v-else
                    :columns="columns"
                    :dataArray="state.rows"
                    dataArrayKey="id"
                    :deleteCallback="null"
                    :editCallback="null"
                    :listState="state.listState"
                    :orderByCallback="orderByCallback"
                    :relatedEntityData="relatedEntityData"
                    :tableLeaveHandler="null"
                />
            </div>
        </NcAppContent>
    </NcContent>
</template>

<style module>
    .content {
        display: flex;
        justify-content: center;
        margin: 16px;
    }

    .page {
        margin-top: 2rem;
        max-width: 1400px;
        width: 100%;
    }

    .topBar {
        margin-bottom: 6px;
        text-align: right;
    }

    .pageTitle {
        margin: 0 0 8px;
        font-size: 28px;
        font-weight: 700;
    }

    .stats {
        color: var(--color-text-maxcontrast);
        margin: 0 0 16px;
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: calc(2*var(--default-grid-baseline));
        margin-bottom: 16px;
    }

    .progress {
        margin-bottom: 16px;
        width: 100%;
    }

    .empty {
        color: var(--color-text-maxcontrast);
        margin: 0;
    }
</style>