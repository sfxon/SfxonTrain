<script setup lang="ts">
import type { ContentSection } from './Types'

defineProps<{
    section: ContentSection
}>()
</script>

<template>
    <section :class="[$style.block, section.columns.length === 2 && $style.twoColumns]">
        <!-- Das HTML stammt aus dem Inhalts-JSON, das von Administrierenden gepflegt wird. -->
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div v-for="(html, i) in section.columns" :key="i" :class="$style.rich" v-html="html" />
    </section>
</template>

<style module>
.block {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 24px;
    margin-block: 16px;
}

.twoColumns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

/*
.rich {
    max-width: 75ch;
    line-height: 1.6;
}
*/

.rich > :first-child {
    margin-top: 0;
}

.rich img,
.rich video {
    max-width: 100%;
    height: auto;
    border-radius: var(--border-radius-large);
}

.rich h2,
.rich h3,
.rich h4 {
    margin: 1.2em 0 0.4em;
    font-weight: 700;
}

.rich ul,
.rich ol {
    padding-inline-start: 1.4em;
    list-style: revert;
}

.rich table {
    border-collapse: collapse;
}

.rich th,
.rich td {
    padding: 6px 10px;
    border: 1px solid var(--color-border);
}

@media (max-width: 800px) {
    .twoColumns {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>