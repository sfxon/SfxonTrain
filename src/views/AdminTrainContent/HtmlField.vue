<script setup lang="ts">

import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'

const props = withDefaults(defineProps<{
    modelValue?: string,
    label: string,
    rows?: number,
}>(), {
    modelValue: '',
    rows: 8,
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void,
}>()

const showPreview = ref(false)

// The preview is sandboxed (no scripts, no access to this page), so pasted HTML cannot do anything here.
const previewDocument = computed(
    () => `<style>body{font-family:sans-serif;font-size:15px;margin:8px;}</style>${props.modelValue}`,
)

function onInput(event: Event) {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}

</script>
<template>
    <div :class="$style.field">
        <div :class="$style.head">
            <span :class="$style.label">{{ label }}</span>
            <NcButton variant="tertiary" @click="showPreview = !showPreview">
                {{ showPreview ? 'Vorschau ausblenden' : 'Vorschau' }}
            </NcButton>
        </div>

        <textarea
            :aria-label="label"
            :class="$style.textarea"
            :rows="rows"
            :value="modelValue"
            spellcheck="false"
            @input="onInput"
        />

        <iframe
            v-if="showPreview"
            :class="$style.preview"
            :srcdoc="previewDocument"
            sandbox=""
            title="Vorschau"
        />
    </div>
</template>

<style module>
    .field {
        display: flex;
        flex-direction: column;
        gap: calc(1*var(--default-grid-baseline));
        min-width: 0;
    }

    .head {
        align-items: center;
        display: flex;
        justify-content: space-between;
    }

    .label {
        font-weight: bold;
    }

    .textarea {
        background-color: var(--color-main-background);
        border: 2px solid var(--color-border-maxcontrast);
        border-radius: var(--border-radius-large);
        color: var(--color-main-text);
        font-family: monospace;
        font-size: 13px;
        padding: calc(2*var(--default-grid-baseline));
        resize: vertical;
        width: 100%;
    }

    .preview {
        background-color: #fff;
        border: 1px solid var(--color-border);
        border-radius: var(--border-radius-large);
        min-height: 160px;
        resize: vertical;
        width: 100%;
    }
</style>