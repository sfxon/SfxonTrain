<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'

import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import CircleOutline from 'vue-material-design-icons/CircleOutline.vue'

import ContentBlock from '@/components/Player/ContentBlock.vue'
import QuizBlock from '@/components/Player/QuizBlock.vue'
import { errorMessage, finishRun, saveProgress, startRun, submitAnswer } from '@/components/Player/Api'
import type { AnswerResult, ClientPage, FinishResult, PlayerState } from '@/components/Player/Types'

const APP_ID = 'sfxontrain'

const state = loadState<PlayerState>(APP_ID, 'player')
const { run, training } = state
const pages = state.content.pages

const overviewUrl = generateUrl('/apps/sfxontrain/')

// Antworten kommen bereits ausgewertet vom Server (inkl. früherer Antworten beim Fortsetzen).
const answers = reactive<Record<string, AnswerResult>>({ ...state.answers })
const visited = ref<string[]>([...run.visitedPageIds])

// Fortsetzen: an der zuletzt gespeicherten Seite weitermachen.
const index = ref(Math.max(0, pages.findIndex((p) => p.id === run.currentPageId)))

const submittingId = ref<string | null>(null)
const finishing = ref(false)
const retrying = ref(false)
const result = ref<FinishResult | null>(null)
const articleRef = ref<HTMLElement | null>(null)

const page = computed(() => pages[index.value])
const isFirst = computed(() => index.value === 0)
const isLast = computed(() => index.value === pages.length - 1)
const percent = computed(() => Math.round((visited.value.length / pages.length) * 100))

function scoredQuestionIds(p: ClientPage): string[] {
    return p.sections.flatMap((s) => (s.type === 'quiz' && s.scored ? s.questions.map((q) => q.id) : []))
}

function questionIds(p: ClientPage): string[] {
    return p.sections.flatMap((s) => (s.type === 'quiz' ? s.questions.map((q) => q.id) : []))
}

function isPageAnswered(p: ClientPage): boolean {
    return questionIds(p).every((id) => id in answers)
}

/** Seite gilt als erledigt: gesehen und alle zählenden Fragen beantwortet. */
function isPageDone(p: ClientPage): boolean {
    return visited.value.includes(p.id) && scoredQuestionIds(p).every((id) => id in answers)
}

const openOnPageCount = computed(() => questionIds(page.value).filter((id) => !(id in answers)).length)
const canContinue = computed(() => openOnPageCount.value === 0)

const openQuestionCount = computed(() =>
    pages.reduce((sum, p) => sum + scoredQuestionIds(p).filter((id) => !(id in answers)).length, 0),
)

// Speichern läuft nacheinander, damit schnelles Blättern nie eine ältere Seite zuletzt speichert.
let saveQueue: Promise<void> = Promise.resolve()

function persist(pageId: string): void {
    saveQueue = saveQueue
        .then(() => saveProgress(run.id, pageId))
        .catch((error: unknown) => {
            showError(errorMessage(error, 'Der Fortschritt konnte nicht gespeichert werden.'))
        })
}

function goTo(target: number): void {
    if (target < 0 || target >= pages.length || target === index.value) {
        return
    }

    if (target > index.value && !pages.slice(index.value, target).every(isPageAnswered)) {
        return
    }

    index.value = target
    const pageId = pages[target].id
    if (!visited.value.includes(pageId)) {
        visited.value.push(pageId)
    }
    persist(pageId)

    void nextTick(() => articleRef.value?.scrollIntoView({ block: 'start' }))
}

async function onAnswer(questionId: string, selected: string[]): Promise<void> {
    if (questionId in answers) {
        return
    }

    submittingId.value = questionId
    try {
        answers[questionId] = await submitAnswer(run.id, questionId, selected)
    } catch (error) {
        showError(errorMessage(error, 'Die Antwort konnte nicht gespeichert werden.'))
    } finally {
        submittingId.value = null
    }
}

async function onFinish(): Promise<void> {
    finishing.value = true
    try {
        await saveQueue
        result.value = await finishRun(run.id)
    } catch (error) {
        showError(errorMessage(error, 'Die Schulung konnte nicht abgeschlossen werden.'))
    } finally {
        finishing.value = false
    }
}

async function onRetry(): Promise<void> {
    retrying.value = true
    try {
        const { url } = await startRun(run.trainUserId)
        window.location.assign(url)
    } catch (error) {
        showError(errorMessage(error, 'Die Schulung konnte nicht neu gestartet werden.'))
        retrying.value = false
    }
}
</script>

<template>
    <NcContent :app-name="APP_ID">
        <NcAppContent :class="$style.content">
            <div :class="$style.player">
                <!--
                <header :class="$style.header">
                    
                    <NcButton variant="tertiary" :href="overviewUrl">
                        <template #icon>
                            <ArrowLeft :size="20" />
                        </template>
                        Zur Übersicht
                    </NcButton>
                    
                    <h1 :class="$style.title">{{ training.title }}</h1>
                    <div v-if="!result" :class="$style.progress">
                        <div :class="$style.progressLabel">
                            <span>Seite {{ index + 1 }} von {{ pages.length }}</span>
                            <strong>{{ percent }} %</strong>
                        </div>
                        <NcProgressBar :value="percent" size="medium" />
                    </div>
                </header>
                -->

                <!-- Ergebnis -->
                <section v-if="result" :class="$style.result" aria-live="polite">
                    <NcNoteCard
                        :type="result.passed ? 'success' : 'error'"
                        :heading="result.passed ? 'Bestanden' : 'Nicht bestanden'">
                        <p>
                            Du hast {{ result.scorePercent }} % erreicht
                            ({{ result.pointsAchieved }} von {{ result.pointsMax }} Punkten).
                            <template v-if="result.passPercent !== null">
                                Zum Bestehen sind {{ result.passPercent }} % nötig.
                            </template>
                        </p>
                    </NcNoteCard>
                    <div :class="$style.resultActions">
                        <NcButton v-if="!result.passed" variant="primary" :disabled="retrying" @click="onRetry">
                            Erneut versuchen
                        </NcButton>
                        <NcButton :variant="result.passed ? 'primary' : 'secondary'" :href="overviewUrl">
                            Zur Übersicht
                        </NcButton>
                    </div>
                </section>

                <!-- Player -->
                <div v-else :class="$style.layout">
                    <!--
                    <nav :class="$style.nav" aria-label="Seitenübersicht">
                        <ol :class="$style.navList">
                            <li v-for="(p, i) in pages" :key="p.id">
                                <button
                                    type="button"
                                    :class="[$style.navItem, i === index && $style.navCurrent]"
                                    :aria-current="i === index ? 'step' : undefined"
                                    @click="goTo(i)">
                                    <CheckCircle v-if="isPageDone(p)" :size="18" :class="$style.iconDone" />
                                    <CircleOutline v-else :size="18" />
                                    <span>{{ p.title }}</span>
                                </button>
                            </li>
                        </ol>
                    </nav>
                -->

                    <article ref="articleRef" :class="$style.article">
                        <!-- <h2 :class="$style.pageTitle">{{ page.title }}</h2> -->

                        <template v-for="s in page.sections" :key="s.id">
                            <ContentBlock v-if="s.type === 'content'" :section="s" />
                            <QuizBlock
                                v-else
                                :section="s"
                                :answers="answers"
                                :submitting-id="submittingId"
                                @answer="onAnswer" />
                        </template>
                    

                        <footer :class="$style.footer">
                            <NcButton variant="secondary" :disabled="isFirst" @click="goTo(index - 1)">
                                <template #icon>
                                    <ChevronLeft :size="20" />
                                </template>
                                Zurück
                            </NcButton>

                            <p v-if="!canContinue" :class="$style.hint">
                                Bitte beantworte zuerst {{ openOnPageCount === 1 ? 'die offene Frage' : `die ${openOnPageCount} offenen Fragen` }}
                                auf dieser Seite.
                            </p>
                            <p v-else-if="isLast && openQuestionCount > 0" :class="$style.hint">
                                Es fehlen noch {{ openQuestionCount }}
                                {{ openQuestionCount === 1 ? 'Antwort' : 'Antworten' }} auf zählende Fragen.
                            </p>

                            <NcButton
                                v-if="!isLast"
                                variant="primary"
                                :disabled="!canContinue"
                                @click="goTo(index + 1)">
                                Weiter
                            </NcButton>
                            <NcButton
                                v-else
                                variant="primary"
                                :disabled="!canContinue || openQuestionCount > 0 || finishing"
                                @click="onFinish">
                                Schulung abschließen
                            </NcButton>
                        </footer>
                    </article>
                </div>
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

.content {
    display: flex;
    justify-content: center;
    margin: 16px;
}

.player {
    /*margin-top: 2rem;*/
    max-width: 1400px;
    width: 100%;
}

.header {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-block-end: 24px;
}

.header > :first-child {
    align-self: flex-start;
}

.title {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
}

.progress {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-width: 480px;
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

/*
.layout {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 32px;
    align-items: start;
}
*/

.nav {
    position: sticky;
    top: 16px;
}

.navList {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.navItem {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    width: 100%;
    padding: 8px 10px;
    border: 0;
    border-radius: var(--border-radius-large);
    background: transparent;
    color: var(--color-main-text);
    font: inherit;
    text-align: start;
    cursor: pointer;
}

.navItem:hover {
    background: var(--color-background-hover);
}

.navItem:focus-visible {
    outline: 2px solid var(--color-main-text);
    outline-offset: 1px;
}

.navItem svg {
    flex-shrink: 0;
    margin-block-start: 1px;
    color: var(--color-text-maxcontrast);
}

.navCurrent {
    background: var(--color-primary-element-light);
    font-weight: 700;
}

.navItem .iconDone svg {
    color: var(--color-element-success);
}

.article {
    min-width: 0;
    scroll-margin-top: 16px;
}

.pageTitle {
    margin: 0 0 8px;
    font-size: 22px;
    font-weight: 700;
}

.footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-block-start: 32px;
    padding-block-start: 16px;
    border-top: 1px solid var(--color-border);
}

.hint {
    flex: 1;
    margin: 0;
    text-align: end;
    color: var(--color-text-maxcontrast);
}

.result {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 640px;
}

.resultActions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

@media (max-width: 900px) {
    .layout {
        grid-template-columns: minmax(0, 1fr);
    }

    /* Auf schmalen Bildschirmen führen Zurück/Weiter durch die Seiten. */
    .nav {
        display: none;
    }
}
</style>