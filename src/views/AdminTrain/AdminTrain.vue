<script setup lang="ts">

import { computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { mdiFileDocumentEdit } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import SfxonTable from '@/components/SfxonTable'

interface AdminTrainState {
    rows: Record<string, unknown>[],
    relatedEntityData: Record<string, { id: number, label: string }[]>,
    pagination: {
        page: number,
        limit: number,
        total: number,
        totalPages: number,
        allowedLimits: number[],
    },
    listState: {
        orderBy: string,
        orderDirection: 'ASC' | 'DESC',
    },
}

const APP_ID = 'sfxontrain'

const state = loadState<AdminTrainState>(APP_ID, 'adminTrain')

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'title', label: 'Titel', sortable: true },
    { key: 'trainCategoryId', label: 'Kategorie', sortable: true, type: 'relatedEntity', relatedEntityName: 'trainCategory' },
    { key: 'versionNumber', label: 'Version', sortable: false },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'passPercent', label: 'Bestehensgrenze (%)', sortable: false },
    { key: 'repeatIntervalMonths', label: 'Wiederholung (Monate)', sortable: false },
    { key: 'active', label: 'Aktiv', sortable: true, type: 'relatedEntity', relatedEntityName: 'active' },
    {
        key: 'actions',
        label: 'Aktionen',
        sortable: false,
        type: 'actions',
        extraActions: [
            {
                label: 'Inhalt bearbeiten',
                icon: mdiFileDocumentEdit,
                visible: (row: Record<string, unknown>) => row.currentVersionId != null,
                callback: (row: Record<string, unknown>) => window.location.assign(
                    generateUrl('/apps/sfxontrain/admin/train/{trainId}/content', { trainId: row.id as number }),
                ),
            },
        ],
    },
]

// "active" is a 0/1 flag. Displaying it via the existing "relatedEntity" column type avoids changes to SfxonTableRow.
const relatedEntityData = computed(() => ({
    ...state.relatedEntityData,
    active: [
        { id: 1, label: 'Ja' },
        { id: 0, label: 'Nein' },
    ],
}))

const pagination = state.pagination
const hasPrev = pagination.page > 1
const hasNext = pagination.page < pagination.totalPages

/** Builds a URL of the current page with changed query parameters. Every change is a full page load. */
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

    window.location.assign(buildUrl({ orderBy: key, orderDirection: direction, page: 1 }))
}

function onLimitChange(event: Event) {
    const limit = Number((event.target as HTMLSelectElement).value)
    window.location.assign(buildUrl({ limit, page: 1 }))
}

function editCallback(row: Record<string, unknown>) {
    window.location.assign(
        generateUrl('/apps/sfxontrain/admin/train/{trainId}/edit', { trainId: row.id as number }),
    )
}

function onForwardToDashboard() {
    window.location.assign(generateUrl('/apps/sfxontrain/'))
}

</script>
<template>
    <NcContent :app-name="APP_ID">
        <NcAppContent :class="$style.content">
            <div :class="$style.page">
                <div :class="$style.topBar">
                    <NcButton
                        variant="secondary"
                        @click="onForwardToDashboard()">
                        Zu Meine Schulungen wechseln
                    </NcButton>
                </div>

                <h1 :class="$style.pageTitle">Trainings</h1>

                <p v-if="state.rows.length === 0" :class="$style.empty">
                    Es sind noch keine Trainings vorhanden.
                </p>

                <SfxonTable
                    v-else
                    :columns="columns"
                    :dataArray="state.rows"
                    dataArrayKey="id"
                    :deleteCallback="null"
                    :editCallback="editCallback"
                    :listState="state.listState"
                    :orderByCallback="orderByCallback"
                    :relatedEntityData="relatedEntityData"
                    :tableLeaveHandler="null"
                />

                <nav :class="$style.pagination" aria-label="Seitennavigation">
                    <NcButton
                        :disabled="!hasPrev"
                        :href="hasPrev ? buildUrl({ page: pagination.page - 1 }) : undefined"
                    >
                        Zurück
                    </NcButton>

                    <span>Seite {{ pagination.page }} von {{ pagination.totalPages }} ({{ pagination.total }} Trainings)</span>

                    <NcButton
                        :disabled="!hasNext"
                        :href="hasNext ? buildUrl({ page: pagination.page + 1 }) : undefined"
                    >
                        Weiter
                    </NcButton>

                    <label :class="$style.limit">
                        Einträge pro Seite
                        <select :value="pagination.limit" @change="onLimitChange">
                            <option v-for="l in pagination.allowedLimits" :key="l" :value="l">{{ l }}</option>
                        </select>
                    </label>
                </nav>
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

    .topBar :global(button) {
        margin-left: auto;
        margin-right: 0;
    }

    .pageTitle {
        margin: 0 0 16px;
        font-size: 28px;
        font-weight: 700;
    }

    .empty {
        color: var(--color-text-maxcontrast);
        margin: 0;
    }

    .pagination {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: calc(3*var(--default-grid-baseline));
        margin-top: calc(3*var(--default-grid-baseline));
    }

    .limit {
        align-items: center;
        display: flex;
        gap: calc(2*var(--default-grid-baseline));
        margin-left: auto;
    }
</style>