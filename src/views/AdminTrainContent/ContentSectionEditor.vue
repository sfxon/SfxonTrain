<script setup lang="ts">

import { computed } from 'vue'
import HtmlField from './HtmlField.vue'
import type { ContentSection } from './types'

const props = defineProps<{
    section: ContentSection,
}>()

const columns = computed(() => props.section.columns as string[])

function setColumnCount(count: number) {
    const list = columns.value

    if (count === 2 && list.length === 1) {
        list.push('')
    } else if (count === 1 && list.length === 2) {
        if (list[1].trim() !== '' && !window.confirm('Die zweite Spalte enthält Inhalt und wird entfernt. Fortfahren?')) {
            return
        }
        list.splice(1)
    }
}

function onColumnCountChange(event: Event) {
    const select = event.target as HTMLSelectElement
    setColumnCount(Number(select.value))
    // Reflect a cancelled change.
    select.value = String(columns.value.length)
}

</script>
<template>
    <div>
        <label :class="$style.columnCount">
            Spalten
            <select :value="columns.length" @change="onColumnCountChange">
                <option value="1">1 Spalte</option>
                <option value="2">2 Spalten</option>
            </select>
        </label>

        <div :class="[$style.columns, columns.length === 2 ? $style.twoColumns : '']">
            <HtmlField
                v-for="(_, index) in columns"
                :key="index"
                v-model="columns[index]"
                :label="columns.length === 2 ? `HTML Spalte ${index + 1}` : 'HTML'"
            />
        </div>
    </div>
</template>

<style module>
    .columnCount {
        align-items: center;
        display: flex;
        gap: calc(2*var(--default-grid-baseline));
        margin-bottom: calc(2*var(--default-grid-baseline));
    }

    .columns {
        display: grid;
        gap: calc(3*var(--default-grid-baseline));
        grid-template-columns: minmax(0, 1fr);
    }

    @media (min-width: 900px) {
        .twoColumns {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>