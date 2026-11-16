<script setup lang="ts">

import { computed, reactive, ref } from 'vue'
import axios from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'

interface AdminTrainEditState {
    train: {
        id: number,
        active: number | null,
        trainCategoryId: number | null,
        currentVersionId: number | null,
        title: string | null,
        description: string | null,
        passPercent: number | null,
        repeatIntervalMonths: number | null,
        versionNumber: number | null,
        status: number | null,
    },
    categories: { id: number, label: string }[],
}

const APP_ID = 'sfxontrain'
const LIST_URL = generateUrl('/apps/sfxontrain/admin/train/')

const state = loadState<AdminTrainEditState>(APP_ID, 'adminTrainEdit')
const train = state.train

// Without a current version there is nothing to edit but the train itself.
const hasVersion = train.currentVersionId !== null

const active = ref(train.active === 1)
const trainCategoryId = ref<number | null>(train.trainCategoryId)
const title = ref(train.title ?? '')
const description = ref(train.description ?? '')
const passPercent = ref(train.passPercent === null ? '' : String(train.passPercent))
const repeatIntervalMonths = ref(train.repeatIntervalMonths === null ? '' : String(train.repeatIntervalMonths))

const isSaving = ref(false)
const generalError = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const pageTitle = computed(() => title.value !== '' ? title.value : `Training ${train.id}`)

function clearFieldError(field: string) {
    delete fieldErrors[field]
}

function clearErrors() {
    generalError.value = ''
    for (const key of Object.keys(fieldErrors)) {
        delete fieldErrors[key]
    }
}

/** Empty input is sent as null, so the backend can tell "cleared" from a number. */
function numberOrNull(value: string): number | null {
    return value.trim() === '' ? null : Number(value)
}

async function submit(): Promise<boolean> {
    clearErrors()
    isSaving.value = true

    const payload: Record<string, unknown> = {
        active: active.value ? 1 : 0,
        trainCategoryId: trainCategoryId.value,
    }

    if (hasVersion) {
        payload.title = title.value
        payload.description = description.value
        payload.passPercent = numberOrNull(passPercent.value)
        payload.repeatIntervalMonths = numberOrNull(repeatIntervalMonths.value)
    }

    try {
        await axios.put(
            generateUrl('/apps/sfxontrain/api/admin/train/{trainId}', { trainId: train.id }),
            payload,
        )
        showSuccess('Gespeichert')
        return true
    } catch (error: any) {
        const data = error?.response?.data

        if (error?.response?.status === 422 && data?.errors) {
            Object.assign(fieldErrors, data.errors)
            generalError.value = 'Bitte die markierten Felder korrigieren.'
        } else {
            generalError.value = 'Beim Speichern ist ein Fehler aufgetreten.'
            console.error('Error while saving train:', error)
        }

        return false
    } finally {
        isSaving.value = false
    }
}

async function save() {
    await submit()
}

async function saveAndBack() {
    if (await submit()) {
        window.location.assign(LIST_URL)
    }
}

function onBackButton() {
    window.location.assign(LIST_URL)
}

</script>
<template>
    <NcContent :app-name="APP_ID">
        <NcAppContent :class="$style.content">
            <div :class="$style.page">
                <div :class="$style.topBar">
                    <NcButton variant="secondary" @click="onBackButton()">
                        Zurück zur Liste
                    </NcButton>
                </div>

                <h1 :class="$style.pageTitle">{{ pageTitle }}</h1>

                <p :class="$style.meta">
                    Training-ID {{ train.id }}
                    <template v-if="hasVersion">
                        · Version {{ train.versionNumber }} · Status {{ train.status }}
                    </template>
                </p>

                <NcNoteCard v-if="generalError" type="error">
                    {{ generalError }}
                </NcNoteCard>

                <NcNoteCard v-if="!hasVersion" type="info">
                    Dieses Training hat noch keine aktuelle Version. Es können nur Aktiv-Status und Kategorie bearbeitet werden.
                </NcNoteCard>

                <form :class="$style.form" @submit.prevent="save">
                    <NcCheckboxRadioSwitch v-model="active" type="switch">
                        Aktiv
                    </NcCheckboxRadioSwitch>

                    <div>
                        <NcSelect
                            v-model="trainCategoryId"
                            input-label="Kategorie"
                            :options="state.categories"
                            :reduce="(option: { id: number }) => option.id"
                            :clearable="true"
                            :class="$style.select"
                            @update:model-value="clearFieldError('trainCategoryId')"
                        />
                        <p v-if="fieldErrors.trainCategoryId" :class="$style.fieldError">
                            {{ fieldErrors.trainCategoryId }}
                        </p>
                    </div>

                    <template v-if="hasVersion">
                        <NcTextField
                            v-model="title"
                            label="Titel"
                            :error="!!fieldErrors.title"
                            :helper-text="fieldErrors.title"
                            @update:model-value="clearFieldError('title')"
                        />

                        <NcTextArea
                            v-model="description"
                            label="Beschreibung"
                            :error="!!fieldErrors.description"
                            :helper-text="fieldErrors.description"
                            @update:model-value="clearFieldError('description')"
                        />

                        <NcTextField
                            v-model="passPercent"
                            label="Bestehensgrenze (%)"
                            type="number"
                            :error="!!fieldErrors.passPercent"
                            :helper-text="fieldErrors.passPercent"
                            @update:model-value="clearFieldError('passPercent')"
                        />

                        <NcTextField
                            v-model="repeatIntervalMonths"
                            label="Wiederholung (Monate)"
                            type="number"
                            :error="!!fieldErrors.repeatIntervalMonths"
                            :helper-text="fieldErrors.repeatIntervalMonths"
                            @update:model-value="clearFieldError('repeatIntervalMonths')"
                        />
                    </template>

                    <div :class="$style.actions">
                        <NcButton :disabled="isSaving" variant="secondary" @click="saveAndBack">
                            Speichern &amp; Zurück
                        </NcButton>
                        <NcButton :disabled="isSaving" variant="primary" @click="save">
                            Speichern
                        </NcButton>
                    </div>
                </form>
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
        max-width: 800px;
        width: 100%;
    }

    .topBar {
        margin-bottom: 6px;
        text-align: right;
    }

    .pageTitle {
        margin: 0 0 4px;
        font-size: 28px;
        font-weight: 700;
    }

    .meta {
        color: var(--color-text-maxcontrast);
        margin: 0 0 16px;
    }

    .form {
        display: flex;
        flex-direction: column;
        gap: calc(3*var(--default-grid-baseline));
    }

    .select {
        width: 100%;
    }

    .fieldError {
        color: var(--color-element-error);
        margin: calc(1*var(--default-grid-baseline)) 0 0;
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: calc(2*var(--default-grid-baseline));
        justify-content: flex-end;
    }
</style>