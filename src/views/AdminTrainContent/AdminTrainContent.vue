<script setup lang="ts">

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { mdiArrowDown, mdiArrowUp, mdiDelete, mdiPlus } from '@mdi/js'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationList from '@nextcloud/vue/components/NcAppNavigationList'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ContentSectionEditor from './ContentSectionEditor.vue'
import QuizSectionEditor from './QuizSectionEditor.vue'
import { createId, createQuestion, moveItem } from './helpers'
import type { TrainingContent, TrainingPage } from './types'

interface AdminTrainContentState {
    train: {
        id: number,
        title: string | null,
        versionNumber: number | null,
    },
    hasVersion: boolean,
    content: TrainingContent | null,
    isNew: boolean,
    loadError: string | null,
}

const APP_ID = 'sfxontrain'
const LIST_URL = generateUrl('/apps/sfxontrain/admin/train/')
const GENERAL = -1

const state = loadState<AdminTrainContentState>(APP_ID, 'adminTrainContent')
const trainUrl = generateUrl('/apps/sfxontrain/admin/train/{trainId}/edit', { trainId: state.train.id })
const fileName = `${state.train.id}_${state.train.versionNumber}.json`

const content = ref<TrainingContent | null>(state.content)
if (content.value && !content.value.meta) {
    content.value.meta = { language: 'de' }
}

/** Index of the page that is edited, or GENERAL for language and duration. */
const selected = ref(0)
const isNew = ref(state.isNew)
const isSaving = ref(false)
const dirty = ref(false)
const saveErrors = ref<string[]>([])

const currentPage = computed<TrainingPage | null>(() => {
    if (content.value === null || selected.value === GENERAL) {
        return null
    }
    return content.value.pages[selected.value] ?? null
})

const estimatedMinutes = computed({
    get: () => content.value?.meta.estimatedMinutes === undefined ? '' : String(content.value.meta.estimatedMinutes),
    set: (value: string) => {
        if (content.value === null) {
            return
        }
        if (String(value).trim() === '') {
            delete content.value.meta.estimatedMinutes
        } else {
            content.value.meta.estimatedMinutes = Number(value)
        }
    },
})

watch(content, () => { dirty.value = true }, { deep: true })

watch(selected, () => {
    document.querySelector('#app-content-vue')?.scrollTo({ top: 0 })
})

function onBeforeUnload(event: BeforeUnloadEvent) {
    if (dirty.value) {
        event.preventDefault()
        event.returnValue = ''
    }
}

onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

function navigate(url: string) {
    if (dirty.value && !window.confirm('Es gibt ungespeicherte Änderungen. Wirklich verlassen?')) {
        return
    }
    dirty.value = false
    window.location.assign(url)
}

function pageTitle(page: TrainingPage, index: number): string {
    return `${index + 1}. ${page.title.trim() !== '' ? page.title : '(ohne Titel)'}`
}

/* Pages */

function addPage() {
    if (content.value === null) {
        return
    }
    content.value.pages.push({ id: createId('page'), title: 'Neue Seite', sections: [] })
    selected.value = content.value.pages.length - 1
}

function removePage(index: number) {
    if (content.value === null) {
        return
    }
    if (content.value.pages.length <= 1) {
        showError('Mindestens eine Seite ist erforderlich.')
        return
    }
    if (!window.confirm('Diese Seite löschen? Fortschritte und Auswertungen, die sich auf die Seite beziehen, können dadurch ins Leere laufen.')) {
        return
    }

    content.value.pages.splice(index, 1)

    if (selected.value > index) {
        selected.value--
    } else if (selected.value === index) {
        selected.value = Math.min(index, content.value.pages.length - 1)
    }
}

function movePage(index: number, delta: -1 | 1) {
    if (content.value === null) {
        return
    }
    const target = moveItem(content.value.pages, index, delta)

    if (selected.value === index) {
        selected.value = target
    } else if (selected.value === target) {
        selected.value = index
    }
}

/* Sections of the current page */

function addContentSection() {
    currentPage.value?.sections.push({ id: createId('section'), type: 'content', columns: [''] })
}

function addQuizSection() {
    currentPage.value?.sections.push({
        id: createId('quiz'),
        type: 'quiz',
        title: '',
        intro: '',
        scored: true,
        shuffleOptions: true,
        questions: [createQuestion()],
    })
}

function removeSection(index: number) {
    if (currentPage.value && window.confirm('Diesen Abschnitt löschen?')) {
        currentPage.value.sections.splice(index, 1)
    }
}

/* Saving */

async function save() {
    saveErrors.value = []
    isSaving.value = true

    try {
        await axios.put(
            generateUrl('/apps/sfxontrain/api/admin/train/{trainId}/content', { trainId: state.train.id }),
            { content: content.value },
        )
        dirty.value = false
        isNew.value = false
        showSuccess('Gespeichert')
    } catch (error: any) {
        const data = error?.response?.data

        if (error?.response?.status === 422 && Array.isArray(data?.errors)) {
            saveErrors.value = data.errors
        } else {
            saveErrors.value = ['Beim Speichern ist ein Fehler aufgetreten.']
            console.error('Error while saving content:', error)
        }
    } finally {
        isSaving.value = false
    }
}

</script>
<template>
    <NcContent :app-name="APP_ID">
        <NcAppNavigation v-if="content">
            <NcAppNavigationList>
                <NcAppNavigationNew text="Seite hinzufügen" @click="addPage">
                    <template #icon>
                        <NcIconSvgWrapper :path="mdiPlus" :size="20" />
                    </template>
                </NcAppNavigationNew>

                <NcAppNavigationItem
                    name="Allgemein"
                    :active="selected === GENERAL"
                    @click="selected = GENERAL"
                />

                <NcAppNavigationItem
                    v-for="(page, index) in content.pages"
                    :key="page.id"
                    :name="pageTitle(page, index)"
                    :active="selected === index"
                    @click="selected = index"
                >
                    <template #actions>
                        <NcActionButton :disabled="index === 0" @click="movePage(index, -1)">
                            <template #icon><NcIconSvgWrapper :path="mdiArrowUp" :size="20" /></template>
                            Nach oben
                        </NcActionButton>
                        <NcActionButton :disabled="index === content.pages.length - 1" @click="movePage(index, 1)">
                            <template #icon><NcIconSvgWrapper :path="mdiArrowDown" :size="20" /></template>
                            Nach unten
                        </NcActionButton>
                        <NcActionButton @click="removePage(index)">
                            <template #icon><NcIconSvgWrapper :path="mdiDelete" :size="20" /></template>
                            Seite löschen
                        </NcActionButton>
                    </template>
                </NcAppNavigationItem>
            </NcAppNavigationList>
        </NcAppNavigation>

        <NcAppContent>
            <div :class="$style.page">
                <header :class="$style.header">
                    <div>
                        <h1 :class="$style.pageTitle">
                            Inhalt: {{ state.train.title || `Training ${state.train.id}` }}
                        </h1>
                        <p :class="$style.meta">
                            <template v-if="state.hasVersion">Version {{ state.train.versionNumber }} · Datei {{ fileName }}</template>
                            <span v-if="dirty" :class="$style.dirty"> · ungespeicherte Änderungen</span>
                        </p>
                    </div>

                    <div :class="$style.headerActions">
                        <NcButton variant="secondary" @click="navigate(LIST_URL)">
                            Zur Liste
                        </NcButton>
                        <NcButton variant="secondary" @click="navigate(trainUrl)">
                            Eigenschaften
                        </NcButton>
                        <NcButton v-if="content" variant="primary" :disabled="isSaving" @click="save">
                            Speichern
                        </NcButton>
                    </div>
                </header>

                <NcNoteCard v-if="state.loadError" type="error">
                    Der vorhandene Inhalt konnte nicht geladen werden: {{ state.loadError }}
                </NcNoteCard>

                <NcNoteCard v-else-if="!state.hasVersion" type="info">
                    Dieses Training hat noch keine aktuelle Version, daher gibt es keinen Inhalt zu bearbeiten.
                </NcNoteCard>

                <template v-else-if="content">
                    <NcNoteCard v-if="isNew" type="info">
                        Für diese Version gibt es noch keinen Inhalt. Beim ersten Speichern wird {{ fileName }} angelegt.
                    </NcNoteCard>

                    <NcNoteCard v-if="saveErrors.length" type="error">
                        <p>Der Inhalt wurde nicht gespeichert:</p>
                        <ul :class="$style.errors">
                            <li v-for="message in saveErrors" :key="message">{{ message }}</li>
                        </ul>
                    </NcNoteCard>

                    <!-- General -->
                    <section v-if="selected === GENERAL" :class="$style.card">
                        <h2 :class="$style.heading">Allgemein</h2>
                        <NcTextField v-model="content.meta.language" label="Sprache (z. B. de)" />
                        <NcTextField v-model="estimatedMinutes" label="Geschätzte Dauer (Minuten)" type="number" />
                    </section>

                    <!-- One page -->
                    <template v-else-if="currentPage">
                        <section :class="$style.card">
                            <h2 :class="$style.heading">Seite {{ selected + 1 }} von {{ content.pages.length }}</h2>
                            <NcTextField v-model="currentPage.title" label="Seitentitel (erscheint links in der Navigation)" />
                            <p :class="$style.meta">Seiten-ID: {{ currentPage.id }} (bleibt gleich)</p>
                        </section>

                        <p v-if="currentPage.sections.length === 0" :class="$style.meta">
                            Diese Seite hat noch keine Abschnitte.
                        </p>

                        <section v-for="(section, sIndex) in currentPage.sections" :key="section.id" :class="$style.card">
                            <div :class="$style.sectionHead">
                                <strong>Abschnitt {{ sIndex + 1 }} · {{ section.type === 'quiz' ? 'Quiz' : 'Inhalt' }}</strong>
                                <span :class="$style.id">ID: {{ section.id }}</span>

                                <span :class="$style.spacer" />

                                <NcButton
                                    variant="tertiary"
                                    aria-label="Abschnitt nach oben"
                                    :disabled="sIndex === 0"
                                    @click="moveItem(currentPage.sections, sIndex, -1)"
                                >
                                    <template #icon><NcIconSvgWrapper :path="mdiArrowUp" :size="20" /></template>
                                </NcButton>
                                <NcButton
                                    variant="tertiary"
                                    aria-label="Abschnitt nach unten"
                                    :disabled="sIndex === currentPage.sections.length - 1"
                                    @click="moveItem(currentPage.sections, sIndex, 1)"
                                >
                                    <template #icon><NcIconSvgWrapper :path="mdiArrowDown" :size="20" /></template>
                                </NcButton>
                                <NcButton variant="tertiary" aria-label="Abschnitt löschen" @click="removeSection(sIndex)">
                                    <template #icon><NcIconSvgWrapper :path="mdiDelete" :size="20" /></template>
                                </NcButton>
                            </div>

                            <ContentSectionEditor v-if="section.type === 'content'" :section="section" />
                            <QuizSectionEditor v-else-if="section.type === 'quiz'" :section="section" />
                        </section>

                        <div :class="$style.addBar">
                            <NcButton variant="secondary" @click="addContentSection">
                                <template #icon><NcIconSvgWrapper :path="mdiPlus" :size="20" /></template>
                                Inhaltsabschnitt hinzufügen
                            </NcButton>
                            <NcButton variant="secondary" @click="addQuizSection">
                                <template #icon><NcIconSvgWrapper :path="mdiPlus" :size="20" /></template>
                                Quiz hinzufügen
                            </NcButton>
                        </div>

                        <div :class="$style.pager">
                            <NcButton :disabled="selected <= 0" @click="selected--">
                                Vorherige Seite
                            </NcButton>
                            <NcButton :disabled="selected >= content.pages.length - 1" @click="selected++">
                                Nächste Seite
                            </NcButton>
                        </div>
                    </template>
                </template>
            </div>
        </NcAppContent>
    </NcContent>
</template>

<style module>
    .page {
        display: flex;
        flex-direction: column;
        gap: calc(3*var(--default-grid-baseline));
        margin: 0 auto;
        max-width: 1100px;
        padding: 0 calc(4*var(--default-grid-baseline)) calc(6*var(--default-grid-baseline));
    }

    .header {
        align-items: center;
        background-color: var(--color-main-background);
        display: flex;
        flex-wrap: wrap;
        gap: calc(3*var(--default-grid-baseline));
        justify-content: space-between;
        padding: calc(3*var(--default-grid-baseline)) 0;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .headerActions {
        display: flex;
        flex-wrap: wrap;
        gap: calc(2*var(--default-grid-baseline));
    }

    .pageTitle {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
    }

    .meta {
        color: var(--color-text-maxcontrast);
        margin: 0;
    }

    .dirty {
        color: var(--color-warning-text);
    }

    .card {
        border: 1px solid var(--color-border);
        border-radius: var(--border-radius-large);
        display: flex;
        flex-direction: column;
        gap: calc(3*var(--default-grid-baseline));
        padding: calc(4*var(--default-grid-baseline));
    }

    .heading {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
    }

    .sectionHead {
        align-items: center;
        display: flex;
        gap: calc(2*var(--default-grid-baseline));
    }

    .id {
        color: var(--color-text-maxcontrast);
        font-size: 12px;
    }

    .spacer {
        flex: 1;
    }

    .addBar,
    .pager {
        display: flex;
        flex-wrap: wrap;
        gap: calc(2*var(--default-grid-baseline));
    }

    .pager {
        justify-content: space-between;
    }

    .errors {
        margin: 0;
        padding-left: calc(5*var(--default-grid-baseline));
    }
</style>