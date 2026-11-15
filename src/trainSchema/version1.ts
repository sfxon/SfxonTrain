/**
 * Schema for the JSON content of a training version (schemaVersion 1).
 */

export interface TrainingContent {
    schemaVersion: 1
    meta: {
        language: string
        estimatedMinutes?: number
    }
    pages: TrainingPage[]
}

export interface TrainingPage {
    /** stable ID, does not change between versions (for progress and evalutation). */
    id: string
    title: string
    sections: TrainingSection[]
}

export type TrainingSection = ContentSection | QuizSection

/**
 * HTML-Content in 1 or 2 Columns.
 */
export interface ContentSection {
    id: string
    type: 'content'
    columns: [string] | [string, string]
}

export interface QuizSection {
    id: string
    type: 'quiz'
    title?: string
    /** Optional HTML-Introduction text. */
    intro?: string
    /** false = Practice questions do not count toward the passing threshold.. */
    scored: boolean
    shuffleOptions?: boolean
    questions: QuizQuestion[]
}

export interface QuizQuestion {
    id: string
    /** single = one right question (Radio), multiple = multiple right questions (Checkbox). */
    type: 'single' | 'multiple'
    text: string
    options: QuizOption[]
    /** Wird nach der Antwort angezeigt. */
    explanation?: string
    /** Standard: 1 */
    points?: number
}

export interface QuizOption {
    id: string
    text: string
    /** Removed by the server, bevor the content is handed to the client. */
    correct: boolean
}