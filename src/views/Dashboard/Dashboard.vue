<script setup lang="ts">
import { ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'

import { generateUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import { errorMessage, startRun } from '@/components/Player/Api'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import Download from 'vue-material-design-icons/Download.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import PlayCircleOutline from 'vue-material-design-icons/PlayCircleOutline.vue'

type TrainingStatus = 'overdue' | 'dueSoon' | 'inProgress' | 'new'

interface OpenTraining {
    id: number
    trainId: number
    category: string
    title: string
    description: string
    status: TrainingStatus
    dueDate: string | null
    daysUntilDue: number | null
    passPercent: number | null
    repeatIntervalMonths: number | null
    runId: number | null
    progress: number | null
}

interface CompletedTraining {
    id: number
    runId: number
    category: string
    title: string
    statusLabel: string
    result: string
    resultLabel: string
    completedAt: string | null
    validUntil: string | null
}

interface Stats {
    open: number
    overdue: number
    completed: number
}

const APP_ID = 'sfxontrain'

const STATUS = {
    overdue: { variant: 'error', icon: AlertOutline },
    dueSoon: { variant: 'warning', icon: ClockOutline },
    inProgress: { variant: 'primary', icon: PlayCircleOutline },
    new: { variant: 'secondary', icon: InformationOutline },
} as const

const isTrainAdmin = loadState(APP_ID, 'isTrainAdmin', false)
const openTrainings = loadState<OpenTraining[]>(APP_ID, 'trainings', [])
const completedTrainings = loadState<CompletedTraining[]>(APP_ID, 'completed', [])
const stats = loadState<Stats>(APP_ID, 'stats', { open: 0, overdue: 0, completed: 0 })

const dateFormat = new Intl.DateTimeFormat('de-DE')

function formatDate(iso: string | null): string {
    return iso ? dateFormat.format(new Date(iso)) : '–'
}

function formatInterval(months: number | null): string {
    if (!months) {
        return 'einmalig'
    }
    if (months === 12) {
        return 'jährlich'
    }
    return `alle ${months} Monate`
}

function statusLabel(t: OpenTraining): string {
    switch (t.status) {
        case 'overdue':
            return 'Überfällig'
        case 'dueSoon':
            return t.daysUntilDue === 0
                ? 'Heute fällig'
                : `Fällig in ${t.daysUntilDue} ${t.daysUntilDue === 1 ? 'Tag' : 'Tagen'}`
        case 'inProgress':
            return 'In Bearbeitung'
        default:
            return 'Neu zugewiesen'
    }
}

function actionLabel(t: OpenTraining): string {
    if (t.runId !== null) {
        return 'Fortsetzen'
    }
    return t.status === 'overdue' ? 'Jetzt starten' : 'Starten'
}

const startingId = ref<number | null>(null)

function onForwardToAdmin() {
    window.location.assign(generateUrl(`/apps/sfxontrain/admin/train/`))
}

async function onStart(training: OpenTraining): Promise<void> {
    startingId.value = training.id
    try {
        const { url } = await startRun(training.id)
        window.location.assign(url)
    } catch (error) {
        showError(errorMessage(error, 'Die Schulung konnte nicht gestartet werden.'))
        startingId.value = null
    }
}

function onDownload(training: CompletedTraining): void {
    window.location.assign(
        generateUrl(`/apps/sfxontrain/api/runs/${training.runId}/certificate`),
    )
}
</script>

<template>
    <NcContent :app-name="APP_ID">
        <NcAppContent :class="$style.content">
            <div :class="$style.page">
                <div :class="$style.adminBar">
                    <NcButton
                        variant="secondary"
                        @click="onForwardToAdmin()"
                        v-if="isTrainAdmin">
                        Zum Admin wechseln
                    </NcButton>
                </div>

                <header :class="$style.header">
                    <div>
                        <h1 :class="$style.pageTitle">Meine Unterweisungen</h1>
                    </div>

                    <ul :class="$style.stats">
                        <li :class="$style.stat">
                            <span :class="$style.statValue">{{ stats.open }}</span>
                            <span :class="$style.statLabel">Zu erledigen</span>
                        </li>
                        <li :class="[$style.stat]">
                            <span :class="$style.statValue">{{ stats.overdue }}</span>
                            <span :class="$style.statLabel">davon überfällig</span>
                        </li>
                        <li :class="$style.stat">
                            <span :class="$style.statValue">{{ stats.completed }}</span>
                            <span :class="$style.statLabel">Absolviert</span>
                        </li>
                    </ul>
                </header>

                <!-- Zu erledigen -->
                <section aria-labelledby="open-heading">
                    <h2 id="open-heading" :class="[$style.sectionTitle, $style.sectionTitleOpenHeading]">
                        Zu erledigen ({{ openTrainings.length }})
                    </h2>

                    <p v-if="openTrainings.length === 0" :class="$style.empty">
                        Alle Unterweisungen wurden absolviert.
                    </p>

                    <div v-else :class="$style.grid">
                        <article v-for="t in openTrainings" :key="t.id" :class="$style.card">
                            <div :class="$style.cardTop">
                                <span :class="$style.category">{{ t.category }}</span>
                                <NcChip
                                    :text="statusLabel(t)"
                                    :variant="STATUS[t.status].variant"
                                    no-close>
                                    <template #icon>
                                        <component :is="STATUS[t.status].icon" :size="16" />
                                    </template>
                                </NcChip>
                            </div>

                            <h3 :class="$style.cardTitle">{{ t.title }}</h3>
                            <p :class="$style.description">{{ t.description }}</p>

                            <dl :class="$style.meta">
                                <div :class="$style.metaRow">
                                    <dt>Fällig:</dt>
                                    <dd :class="{ [$style.isError]: t.status === 'overdue' }">
                                        {{ formatDate(t.dueDate) }}
                                    </dd>
                                </div>
                                <div v-if="t.passPercent !== null" :class="$style.metaRow">
                                    <dt>Bestehen:</dt>
                                    <dd>ab {{ t.passPercent }} %</dd>
                                </div>
                                <div :class="$style.metaRow">
                                    <dt>Turnus:</dt>
                                    <dd>{{ formatInterval(t.repeatIntervalMonths) }}</dd>
                                </div>
                            </dl>

                            <div v-if="t.progress !== null" :class="$style.progress">
                                <div :class="$style.progressLabel">
                                    <span>Fortschritt</span>
                                    <strong>{{ t.progress }} %</strong>
                                </div>
                                <NcProgressBar :value="t.progress" size="medium" />
                            </div>

                            <NcButton
                                :class="$style.action"
                                variant="primary"
                                wide
                                :disabled="startingId !== null"
                                @click="onStart(t)">
                                {{ actionLabel(t) }}
                            </NcButton>
                        </article>
                    </div>
                </section>

                <!-- Absolviert -->
                <section v-if="completedTrainings.length > 0" aria-labelledby="done-heading">
                    <h2 id="done-heading" :class="[$style.sectionTitle, $style.sectionTitleCompletedTrainings]">
                        Absolviert ({{ completedTrainings.length }})
                    </h2>

                    <div :class="$style.grid">
                        <article
                            v-for="t in completedTrainings"
                            :key="t.id"
                            :class="[$style.card, $style.cardDone]">
                            <div :class="$style.cardTop">
                                <span :class="$style.category">{{ t.category }}</span>
                                <NcChip :text="t.statusLabel" variant="success" no-close>
                                    <template #icon>
                                        <CheckCircleOutline :size="16" />
                                    </template>
                                </NcChip>
                            </div>

                            <div :class="$style.resultRow">
                                <h3 :class="$style.cardTitle">{{ t.title }}</h3>
                                <div :class="$style.result">
                                    <span :class="$style.resultValue">{{ t.result }}</span>
                                    <span :class="$style.resultLabel">{{ t.resultLabel }}</span>
                                </div>
                            </div>

                            <dl :class="$style.meta">
                                <div :class="$style.metaRow">
                                    <dt>Abgeschlossen:</dt>
                                    <dd>{{ formatDate(t.completedAt) }}</dd>
                                </div>
                                <div :class="$style.metaRow">
                                    <dt>Gültig bis:</dt>
                                    <dd>{{ t.validUntil ? formatDate(t.validUntil) : 'unbefristet' }}</dd>
                                </div>
                            </dl>

                            <NcButton
                                :class="$style.action"
                                variant="secondary"
                                wide
                                @click="onDownload(t)">
                                <template #icon>
                                    <Download :size="20" />
                                </template>
                                Nachweis als PDF
                            </NcButton>
                        </article>
                    </div>
                </section>
            </div>
        </NcAppContent>
    </NcContent>
</template>

<style module>
:global(header) :global(.app-menu) {
    display: none!important;
}

:global(header) :global(.header-end) :global(.unified-search-menu),
:global(header) :global(.header-end) :global(#notifications),
:global(header) :global(.header-end) :global(.contactsmenu) {
    display: none!important;
}

.adminBar {
    margin-bottom: 6px;
    text-align: right;
}

.adminBar :global(button) {
    margin-left: auto;
    margin-right: 0;
}

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

.header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-block-end: 24px;
}

.pageTitle {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
}

.subtitle {
    margin: 4px 0 0;
    color: var(--color-text-maxcontrast);
}

.stats {
    display: flex;
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.stat {
    display: flex;
    flex-direction: column;
    min-width: 120px;
    padding: 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    background: var(--color-main-background);
}

.statValue {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
}

.statLabel {
    font-size: var(--font-size-small, 13px);
    color: var(--color-text-maxcontrast);
}

.statError {
    border-color: var(--color-border-error);
    background: color-mix(in srgb, var(--color-error) 10%, var(--color-main-background));
}

.statError .statValue,
.statError .statLabel {
    color: var(--color-element-error);
}

.sectionTitle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 24px 0 12px;
    font-size: 20px;
    font-weight: 700;
}

.empty {
    margin: 0 0 2rem;
    color: var(--color-text-maxcontrast);
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 16px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-large);
    background: #EEE;
}

.cardDone {
    background: var(--color-background-hover);
}

.cardTop {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.category {
    font-size: var(--font-size-small, 13px);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--color-text-maxcontrast);
}

.cardTitle {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
}

.description {
    margin: 0;
    color: var(--color-text-maxcontrast);
}

.meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 0;
}

.metaRow {
    display: flex;
    gap: 6px;
}

.metaRow dt {
    color: var(--color-text-maxcontrast);
}

.metaRow dd {
    margin: 0;
    font-weight: 600;
}

.metaRow dd.isError {
    color: var(--color-element-error);
}

.progress {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.progressLabel {
    display: flex;
    justify-content: space-between;
    font-size: var(--font-size-small, 13px);
    color: var(--color-text-maxcontrast);
}

.progressLabel strong {
    color: var(--color-main-text);
}

.action {
    margin-block-start: auto;
}

.resultRow {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.result {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
}

.resultValue {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--color-element-success);
}

.resultLabel {
    font-size: var(--font-size-small, 13px);
    color: var(--color-text-maxcontrast);
}

@media (max-width: 600px) {
    .stats {
        width: 100%;
    }

    .stat {
        flex: 1;
        min-width: 0;
    }
}

.metaRow :global(dt) {
    padding: 0;
    text-align: start;
    width: auto;
}

.metaRow :global(dd) {
    padding: 0;
}

.sectionTitleCompletedTrainings {
    padding: 12px;
    background-color: #065f20;
    color: #FFF;
}

.sectionTitleOpenHeading {
    padding: 12px;
    background-color: #e7b24d;
    color: #FFF;
}

:global(.nc-chip) :global(.nc-chip__text) {
    font-size: 12px!important;
    font-weight: bold!important;
}
</style>