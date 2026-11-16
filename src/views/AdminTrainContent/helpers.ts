import type { QuizOption, QuizQuestion } from './types'

/** IDs of new pages, sections and questions. Existing IDs are never changed (progress and evaluation refer to them). */
export function createId(prefix: string): string {
    return `${prefix}-${Math.random().toString(36).slice(2, 8)}`
}

/** Moves an item by one position and returns its new index. */
export function moveItem<T>(list: T[], index: number, delta: -1 | 1): number {
    const target = index + delta
    if (target < 0 || target >= list.length) {
        return index
    }

    const [item] = list.splice(index, 1)
    list.splice(target, 0, item)

    return target
}

export function nextOptionId(options: QuizOption[]): string {
    const used = new Set(options.map((option) => option.id))

    for (const letter of 'abcdefghijklmnopqrstuvwxyz') {
        if (!used.has(letter)) {
            return letter
        }
    }

    return createId('o')
}

export function createQuestion(): QuizQuestion {
    return {
        id: createId('q'),
        type: 'single',
        text: '',
        options: [
            { id: 'a', text: '', correct: true },
            { id: 'b', text: '', correct: false },
        ],
    }
}