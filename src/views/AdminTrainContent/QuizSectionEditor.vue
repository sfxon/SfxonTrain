<script setup lang="ts">

import { mdiArrowDown, mdiArrowUp, mdiDelete, mdiPlus } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import HtmlField from './HtmlField.vue'
import { createQuestion, moveItem, nextOptionId } from './helpers'
import type { QuizQuestion, QuizSection } from './types'

const props = defineProps<{
    section: QuizSection,
}>()

function addQuestion() {
    props.section.questions.push(createQuestion())
}

function removeQuestion(index: number) {
    if (props.section.questions.length <= 1) {
        return
    }
    if (window.confirm('Diese Frage löschen? Auswertungen, die sich auf die Frage beziehen, können dadurch ins Leere laufen.')) {
        props.section.questions.splice(index, 1)
    }
}

function addOption(question: QuizQuestion) {
    question.options.push({ id: nextOptionId(question.options), text: '', correct: false })
}

function removeOption(question: QuizQuestion, index: number) {
    if (question.options.length <= 2) {
        return
    }

    const [removed] = question.options.splice(index, 1)

    // A single choice question always needs exactly one right answer.
    if (question.type === 'single' && removed.correct) {
        question.options[0].correct = true
    }
}

/** Single choice: exactly one right answer. */
function setSingleCorrect(question: QuizQuestion, index: number) {
    question.options.forEach((option, optionIndex) => {
        option.correct = optionIndex === index
    })
}

function onTypeChange(question: QuizQuestion) {
    if (question.type !== 'single') {
        return
    }

    const first = question.options.findIndex((option) => option.correct)
    setSingleCorrect(question, first === -1 ? 0 : first)
}

function setPoints(question: QuizQuestion, value: string | number) {
    const text = String(value).trim()

    if (text === '') {
        delete question.points
    } else {
        question.points = Number(text)
    }
}

</script>
<template>
    <div :class="$style.quiz">
        <NcTextField v-model="section.title" label="Titel des Quiz" />

        <HtmlField v-model="section.intro" label="Einleitung (HTML, optional)" :rows="4" />

        <div :class="$style.switches">
            <NcCheckboxRadioSwitch v-model="section.scored" type="switch">
                Zählt für das Bestehen
            </NcCheckboxRadioSwitch>
            <NcCheckboxRadioSwitch v-model="section.shuffleOptions" type="switch">
                Antworten mischen
            </NcCheckboxRadioSwitch>
        </div>

        <div v-for="(question, qIndex) in section.questions" :key="question.id" :class="$style.question">
            <div :class="$style.questionHead">
                <strong>Frage {{ qIndex + 1 }}</strong>
                <span :class="$style.id">ID: {{ question.id }}</span>

                <span :class="$style.spacer" />

                <NcButton
                    variant="tertiary"
                    aria-label="Frage nach oben"
                    :disabled="qIndex === 0"
                    @click="moveItem(section.questions, qIndex, -1)"
                >
                    <template #icon><NcIconSvgWrapper :path="mdiArrowUp" :size="20" /></template>
                </NcButton>
                <NcButton
                    variant="tertiary"
                    aria-label="Frage nach unten"
                    :disabled="qIndex === section.questions.length - 1"
                    @click="moveItem(section.questions, qIndex, 1)"
                >
                    <template #icon><NcIconSvgWrapper :path="mdiArrowDown" :size="20" /></template>
                </NcButton>
                <NcButton
                    variant="tertiary"
                    aria-label="Frage löschen"
                    :disabled="section.questions.length <= 1"
                    @click="removeQuestion(qIndex)"
                >
                    <template #icon><NcIconSvgWrapper :path="mdiDelete" :size="20" /></template>
                </NcButton>
            </div>

            <div :class="$style.row">
                <label :class="$style.typeSelect">
                    Typ
                    <select v-model="question.type" @change="onTypeChange(question)">
                        <option value="single">Einzelauswahl (eine richtige Antwort)</option>
                        <option value="multiple">Mehrfachauswahl (mehrere richtige Antworten)</option>
                    </select>
                </label>

                <NcTextField
                    :model-value="question.points ?? ''"
                    label="Punkte (leer = 1)"
                    type="number"
                    @update:model-value="setPoints(question, $event)"
                />
            </div>

            <NcTextField v-model="question.text" label="Fragetext" />

            <div :class="$style.options">
                <strong>Antworten</strong>

                <div v-for="(option, oIndex) in question.options" :key="option.id" :class="$style.option">
                    <label :class="$style.correct">
                        <input
                            v-if="question.type === 'single'"
                            type="radio"
                            :name="`correct-${question.id}`"
                            :checked="option.correct"
                            @change="setSingleCorrect(question, oIndex)"
                        />
                        <input v-else v-model="option.correct" type="checkbox" />
                        richtig
                    </label>

                    <div :class="$style.optionText">
                        <NcTextField v-model="option.text" :label="`Antwort ${option.id}`" />
                    </div>

                    <NcButton
                        variant="tertiary"
                        aria-label="Antwort löschen"
                        :disabled="question.options.length <= 2"
                        @click="removeOption(question, oIndex)"
                    >
                        <template #icon><NcIconSvgWrapper :path="mdiDelete" :size="20" /></template>
                    </NcButton>
                </div>

                <div>
                    <NcButton variant="secondary" @click="addOption(question)">
                        <template #icon><NcIconSvgWrapper :path="mdiPlus" :size="20" /></template>
                        Antwort hinzufügen
                    </NcButton>
                </div>
            </div>

            <HtmlField
                v-model="question.explanation"
                label="Erklärung (HTML, wird nach der Antwort angezeigt)"
                :rows="4"
            />
        </div>

        <div>
            <NcButton variant="secondary" @click="addQuestion">
                <template #icon><NcIconSvgWrapper :path="mdiPlus" :size="20" /></template>
                Frage hinzufügen
            </NcButton>
        </div>
    </div>
</template>

<style module>
    .quiz {
        display: flex;
        flex-direction: column;
        gap: calc(3*var(--default-grid-baseline));
    }

    .switches {
        display: flex;
        flex-wrap: wrap;
        gap: calc(4*var(--default-grid-baseline));
    }

    .question {
        background-color: var(--color-background-hover);
        border-radius: var(--border-radius-large);
        display: flex;
        flex-direction: column;
        gap: calc(3*var(--default-grid-baseline));
        padding: calc(3*var(--default-grid-baseline));
    }

    .questionHead {
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

    .row {
        align-items: end;
        display: grid;
        gap: calc(3*var(--default-grid-baseline));
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    }

    .typeSelect {
        display: flex;
        flex-direction: column;
        gap: calc(1*var(--default-grid-baseline));
    }

    .options {
        display: flex;
        flex-direction: column;
        gap: calc(2*var(--default-grid-baseline));
    }

    .option {
        align-items: center;
        display: flex;
        gap: calc(2*var(--default-grid-baseline));
    }

    .optionText {
        flex: 1;
        min-width: 0;
    }

    .correct {
        align-items: center;
        display: flex;
        gap: calc(1*var(--default-grid-baseline));
        white-space: nowrap;
    }
</style>