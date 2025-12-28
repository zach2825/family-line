<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue', 'parsed', 'submit']);

const inputText = ref('');
const isLoading = ref(false);
const error = ref('');
const followUpQuestion = ref(null);
const parsedData = ref(null);

const parseInput = async () => {
    if (!inputText.value || inputText.value.length < 5) {
        return;
    }

    isLoading.value = true;
    error.value = '';

    try {
        const response = await axios.post(route('api.timeline.parse'), {
            text: inputText.value,
        });

        if (response.data.success) {
            parsedData.value = response.data.data;
            emit('parsed', response.data.data);
            emit('update:modelValue', response.data.data);

            // Check if we need follow-up
            if (response.data.data.missing_fields?.length > 0) {
                await getFollowUp(response.data.data, response.data.data.missing_fields);
            } else {
                followUpQuestion.value = null;
            }
        }
    } catch (err) {
        console.error('Parse error:', err);
        // Silently fail - the local parsing in the form will still work
    } finally {
        isLoading.value = false;
    }
};

const getFollowUp = async (currentData, missingFields) => {
    try {
        const response = await axios.post(route('api.timeline.follow-up'), {
            current_data: currentData,
            missing_fields: missingFields,
        });

        if (response.data.success) {
            followUpQuestion.value = response.data.data;
        }
    } catch (err) {
        console.error('Follow-up error:', err);
    }
};

const parseNow = () => {
    parseInput();
};

const handleSubmit = () => {
    if (parsedData.value) {
        emit('submit', parsedData.value);
    }
};

const handleFollowUpAnswer = (answer) => {
    if (followUpQuestion.value && parsedData.value) {
        parsedData.value[followUpQuestion.value.field] = answer;
        emit('update:modelValue', parsedData.value);

        // Clear the follow-up
        const remaining = parsedData.value.missing_fields?.filter(
            f => f !== followUpQuestion.value.field
        ) || [];

        if (remaining.length > 0) {
            getFollowUp(parsedData.value, remaining);
        } else {
            followUpQuestion.value = null;
        }
    }
};

const clearInput = () => {
    inputText.value = '';
    parsedData.value = null;
    followUpQuestion.value = null;
    error.value = '';
};
</script>

<template>
    <div class="space-y-4">
        <!-- Main Input -->
        <div class="relative">
            <textarea
                v-model="inputText"
                rows="3"
                class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 dark:focus:border-amber-600 focus:ring-amber-500 dark:focus:ring-amber-600 rounded-lg shadow-sm resize-none"
                placeholder="Describe your event naturally, e.g., 'Mom's 50th birthday party at Grandma's house with the whole family'"
            />
            <div v-if="isLoading" class="absolute right-3 top-3">
                <svg class="animate-spin h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                AI will automatically extract: event type, date, people involved, and location
            </p>
            <button
                v-if="inputText && inputText.length >= 5 && !isLoading"
                @click="parseNow"
                type="button"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/30 hover:bg-amber-200 dark:hover:bg-amber-900/50 rounded-md transition"
            >
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Parse Now
            </button>
        </div>

        <InputError v-if="error" :message="error" />

        <!-- Parsed Preview -->
        <div v-if="parsedData && Object.keys(parsedData).length > 0" class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="font-medium text-amber-800 dark:text-amber-200">Detected Information</h4>
                <span class="text-xs text-amber-600 dark:text-amber-400">
                    {{ Math.round((parsedData.confidence || 0) * 100) }}% confident
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm">
                <div v-if="parsedData.title">
                    <span class="text-gray-500 dark:text-gray-400">Title:</span>
                    <span class="ml-1 text-gray-900 dark:text-white">{{ parsedData.title }}</span>
                </div>
                <div v-if="parsedData.event_type">
                    <span class="text-gray-500 dark:text-gray-400">Type:</span>
                    <span class="ml-1 capitalize text-gray-900 dark:text-white">{{ parsedData.event_type }}</span>
                </div>
                <div v-if="parsedData.event_date">
                    <span class="text-gray-500 dark:text-gray-400">Date:</span>
                    <span class="ml-1 text-gray-900 dark:text-white">{{ parsedData.event_date }}</span>
                </div>
                <div v-if="parsedData.location">
                    <span class="text-gray-500 dark:text-gray-400">Location:</span>
                    <span class="ml-1 text-gray-900 dark:text-white">{{ parsedData.location }}</span>
                </div>
            </div>

            <div v-if="parsedData.people_involved?.length > 0" class="text-sm">
                <span class="text-gray-500 dark:text-gray-400">People:</span>
                <span class="ml-1">
                    <span
                        v-for="(person, i) in parsedData.people_involved"
                        :key="i"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-800 dark:text-amber-100 mr-1"
                    >
                        {{ person }}
                    </span>
                </span>
            </div>
        </div>

        <!-- Follow-up Question -->
        <div v-if="followUpQuestion" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
            <p class="text-blue-800 dark:text-blue-200 font-medium mb-2">
                {{ followUpQuestion.question }}
            </p>
            <div v-if="followUpQuestion.suggestions?.length > 0" class="flex flex-wrap gap-2 mb-2">
                <button
                    v-for="suggestion in followUpQuestion.suggestions"
                    :key="suggestion"
                    @click="handleFollowUpAnswer(suggestion)"
                    type="button"
                    class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 dark:bg-blue-800 dark:hover:bg-blue-700 text-blue-800 dark:text-blue-100 rounded-full transition"
                >
                    {{ suggestion }}
                </button>
            </div>
            <input
                type="text"
                @keyup.enter="handleFollowUpAnswer($event.target.value); $event.target.value = ''"
                class="w-full text-sm border-blue-300 dark:border-blue-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md"
                placeholder="Type your answer and press Enter"
            />
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center">
            <button
                v-if="inputText"
                @click="clearInput"
                type="button"
                class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                Clear
            </button>
            <div v-else></div>

            <PrimaryButton
                v-if="parsedData"
                @click="handleSubmit"
                type="button"
                class="bg-amber-600 hover:bg-amber-700"
            >
                Use This & Continue
            </PrimaryButton>
        </div>
    </div>
</template>
