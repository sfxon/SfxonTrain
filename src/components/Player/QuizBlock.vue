<script setup lang="ts">
import { reactive } from 'vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import type { AnswerResult, ClientOption, ClientQuestion, ClientQuizSection } from './Types'

const props = defineProps<{
    section: ClientQuizSection
    answers: Record<string, AnswerResult>
    /** ID der Frage, deren Antwort gerade zum Server unterwegs ist. */
    submittingId: string | null
}>()

const emit = defineEmits<{
    answer: [questionId: string, selected: string[]]
    reset: [questionId: string]
}>()

/** Noch nicht abgeschickte Auswahl je Frage. */
const selection = reactive<Record<string, string[]>>({})

function shuffle<T>(items: T[]): T[] {
    const copy = [...items]
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1))
        ;[copy[i], copy[j]] = [copy[j], copy[i]]
    }
    return copy
}

// Reihenfolge einmalig festlegen, damit sie sich beim Antworten nicht ändert.
const optionsByQuestion: Record<string, ClientOption[]> = Object.fromEntries(
    props.section.questions.map((q) => [q.id, props.section.shuffleOptions ? shuffle(q.options) : q.options]),
)

function result(questionId: string): AnswerResult | undefined {
    return props.answers[questionId]
}

function current(questionId: string): string[] {
    return result(questionId)?.selected ?? selection[questionId] ?? []
}

function setSingle(questionId: string, value: unknown): void {
    selection[questionId] = [String(value)]
}

function setMultiple(questionId: string, value: unknown): void {
    selection[questionId] = Array.isArray(value) ? value.map(String) : []
}

function optionState(questionId: string, optionId: string): 'correct' | 'wrong' | null {
    const r = result(questionId)
    if (!r) {
        return null
    }
    if (r.correctOptionIds.includes(optionId)) {
        return 'correct'
    }
    return r.selected.includes(optionId) ? 'wrong' : null
}

function optionTag(questionId: string, optionId: string): string {
    const state = optionState(questionId, optionId)
    if (state === 'correct') {
        return 'Richtig'
    }
    return state === 'wrong' ? 'Falsch' : ''
}

function submit(question: ClientQuestion): void {
    emit('answer', question.id, current(question.id))
}

function reset(questionId: string): void {
    selection[questionId] = []
    emit('reset', questionId)
}

function pointsLabel(r: AnswerResult): string {
    return `${r.points} von ${r.pointsMax} ${r.pointsMax === 1 ? 'Punkt' : 'Punkten'}`
}
</script>

<template>
    <section :class="$style.quiz" :aria-label="section.title || 'Quiz'">
        <header :class="$style.head">
            <h3 v-if="section.title" :class="$style.title">{{ section.title }}</h3>
            <p :class="$style.scope">
                {{ section.scored
                    ? 'Diese Fragen zählen für das Bestehen.'
                    : 'Übungsfragen – sie zählen nicht für dein Ergebnis.' }}
            </p>
            <!-- Einleitung ist HTML aus dem Inhalts-JSON (Administrierende). -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-if="section.intro" :class="$style.intro" v-html="section.intro" />
        </header>

        <fieldset v-for="q in section.questions" :key="q.id" :class="$style.question">
            <legend :class="$style.legend">{{ q.text }}</legend>
            <p :class="$style.hint">
                {{ q.type === 'multiple' ? 'Mehrere Antworten möglich.' : 'Eine Antwort ist richtig.' }}
                <template v-if="section.scored && (q.points ?? 1) > 1">
                    Bis zu {{ q.points }} Punkte.
                </template>
            </p>

            <div
                v-for="o in optionsByQuestion[q.id]"
                :key="o.id"
                :class="[
                    $style.option,
                    optionState(q.id, o.id) === 'correct' && $style.optionCorrect,
                    optionState(q.id, o.id) === 'wrong' && $style.optionWrong,
                ]">
                <NcCheckboxRadioSwitch
                    v-if="q.type === 'single'"
                    type="radio"
                    :name="q.id"
                    :value="o.id"
                    :model-value="current(q.id)[0] ?? ''"
                    :disabled="!!result(q.id)"
                    @update:model-value="setSingle(q.id, $event)">
                    {{ o.text }}
                </NcCheckboxRadioSwitch>
                <NcCheckboxRadioSwitch
                    v-else
                    type="checkbox"
                    :value="o.id"
                    :model-value="current(q.id)"
                    :disabled="!!result(q.id)"
                    @update:model-value="setMultiple(q.id, $event)">
                    {{ o.text }}
                </NcCheckboxRadioSwitch>
                <span v-if="optionTag(q.id, o.id)" :class="$style.tag">{{ optionTag(q.id, o.id) }}</span>
            </div>

            <div
                v-if="result(q.id)"
                :class="[$style.feedback, result(q.id)!.correct ? $style.feedbackOk : $style.feedbackBad]"
                role="status">
                <strong>{{ result(q.id)!.correct ? 'Richtig.' : 'Leider falsch.' }}</strong>
                <span v-if="section.scored"> {{ pointsLabel(result(q.id)!) }}</span>
                <p v-if="result(q.id)!.explanation" :class="$style.explanation">
                    {{ result(q.id)!.explanation }}
                </p>
            </div>

            <div :class="$style.actions">
                <NcButton
                    v-if="!result(q.id)"
                    variant="secondary"
                    :disabled="current(q.id).length === 0 || submittingId === q.id"
                    @click="submit(q)">
                    Antwort prüfen
                </NcButton>
                <NcButton v-else-if="!section.scored" variant="tertiary" @click="reset(q.id)">
                    Nochmal versuchen
                </NcButton>
            </div>
        </fieldset>
    </section>
</template>

<style module>
.quiz {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-block: 24px;
    padding: 16px 20px;
    border: 1px solid var(--color-border);
    border-inline-start: 4px solid var(--color-primary-element);
    border-radius: var(--border-radius-large);
    background: var(--color-main-background);
}

.head {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
}

.scope {
    margin: 0;
    color: var(--color-text-maxcontrast);
}

.intro {
    max-width: 75ch;
    line-height: 1.6;
}

.question {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    margin: 0;
    padding: 0 0 16px;
    border: 0;
    border-bottom: 1px solid var(--color-border);
}

.question:last-of-type {
    padding-bottom: 0;
    border-bottom: 0;
}

.legend {
    padding: 0;
    font-size: 16px;
    font-weight: 600;
    line-height: 1.4;
}

.hint {
    margin: 0 0 8px;
    font-size: var(--font-size-small, 13px);
    color: var(--color-text-maxcontrast);
}

.option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border-radius: var(--border-radius-large);
}

.optionCorrect {
    background: color-mix(in srgb, var(--color-success) 12%, var(--color-main-background));
}

.optionWrong {
    background: color-mix(in srgb, var(--color-error) 12%, var(--color-main-background));
}

.tag {
    flex-shrink: 0;
    padding-inline-end: 12px;
    font-size: var(--font-size-small, 13px);
    font-weight: 600;
}

.optionCorrect .tag {
    color: var(--color-element-success);
}

.optionWrong .tag {
    color: var(--color-element-error);
}

.feedback {
    margin-block-start: 8px;
    padding: 10px 12px;
    border-radius: var(--border-radius-large);
}

.feedbackOk {
    border-inline-start: 4px solid var(--color-element-success);
    background: color-mix(in srgb, var(--color-success) 10%, var(--color-main-background));
}

.feedbackBad {
    border-inline-start: 4px solid var(--color-element-error);
    background: color-mix(in srgb, var(--color-error) 10%, var(--color-main-background));
}

.explanation {
    margin: 6px 0 0;
    line-height: 1.5;
}

.actions {
    display: flex;
    margin-block-start: 8px;
}

.actions:empty {
    display: none;
}
</style>