import type {
    ContentSection,
    QuizOption,
    QuizQuestion,
    QuizSection,
    TrainingContent,
    TrainingPage,
} from '../../trainSchema/version1'

export type { ContentSection }
export type ClientOption = Omit<QuizOption, 'correct'>
export type ClientQuestion = Omit<QuizQuestion, 'options' | 'explanation'> & {
    options: ClientOption[]
}
export type ClientQuizSection = Omit<QuizSection, 'questions'> & {
    questions: ClientQuestion[]
}
export type ClientSection = ContentSection | ClientQuizSection
export type ClientPage = Omit<TrainingPage, 'sections'> & { sections: ClientSection[] }
export type ClientContent = Omit<TrainingContent, 'pages'> & { pages: ClientPage[] }

/** Serverseitig ausgewertetes Ergebnis einer Frage. */
export interface AnswerResult {
    questionId: string
    selected: string[]
    correct: boolean
    points: number
    pointsMax: number
    correctOptionIds: string[]
    explanation: string | null
}

export interface RunState {
    id: number
    trainUserId: number
    attemptNo: number | null
    currentPageId: string
    visitedPageIds: string[]
    finished: boolean
}

export interface PlayerState {
    run: RunState
    training: {
        title: string
        description: string
        passPercent: number | null
    }
    content: ClientContent
    answers: Record<string, AnswerResult>
}

export interface FinishResult {
    passed: boolean
    scorePercent: number
    pointsAchieved: number
    pointsMax: number
    passPercent: number | null
}

export interface StartResult {
    runId: number
    resumed: boolean
    url: string
}