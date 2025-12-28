<script setup>
const props = defineProps({
    node: Object,
    level: Number,
    members: Array,
});

const emit = defineEmits(['select']);

const getColor = (level) => {
    const colors = [
        'from-amber-500 to-orange-500',
        'from-blue-500 to-indigo-500',
        'from-green-500 to-teal-500',
        'from-purple-500 to-pink-500',
        'from-red-500 to-rose-500',
    ];
    return colors[level % colors.length];
};
</script>

<template>
    <div class="flex flex-col items-center">
        <!-- Node with spouse -->
        <div class="flex items-center gap-2 mb-4">
            <!-- Main member -->
            <div
                @click="emit('select', node)"
                class="cursor-pointer transform hover:scale-105 transition"
            >
                <div
                    :class="[
                        'w-16 h-16 rounded-full flex items-center justify-center text-white font-bold shadow-lg bg-gradient-to-br',
                        getColor(level)
                    ]"
                >
                    {{ node.first_name?.[0] }}{{ node.last_name?.[0] || '' }}
                </div>
                <div class="text-center mt-1">
                    <div class="text-xs font-medium text-gray-900 dark:text-white truncate max-w-20">
                        {{ node.nickname || node.first_name }}
                    </div>
                </div>
            </div>

            <!-- Spouse(s) -->
            <template v-if="node.spouseNodes?.length">
                <div class="w-4 h-0.5 bg-pink-400"></div>
                <div
                    v-for="spouse in node.spouseNodes"
                    :key="spouse.id"
                    @click="emit('select', spouse)"
                    class="cursor-pointer transform hover:scale-105 transition"
                >
                    <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold shadow-lg bg-gradient-to-br from-pink-500 to-rose-500">
                        {{ spouse.first_name?.[0] }}{{ spouse.last_name?.[0] || '' }}
                    </div>
                    <div class="text-center mt-1">
                        <div class="text-xs font-medium text-gray-900 dark:text-white truncate max-w-20">
                            {{ spouse.nickname || spouse.first_name }}
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Children -->
        <div v-if="node.childNodes?.length" class="relative">
            <!-- Vertical line -->
            <div class="absolute left-1/2 -top-4 w-0.5 h-4 bg-gray-300 dark:bg-gray-600 -translate-x-1/2"></div>

            <!-- Horizontal connector -->
            <div v-if="node.childNodes.length > 1" class="absolute top-0 h-0.5 bg-gray-300 dark:bg-gray-600" style="left: 25%; right: 25%;"></div>

            <div class="flex gap-8 pt-4">
                <div v-for="child in node.childNodes" :key="child.id" class="relative">
                    <!-- Vertical line to child -->
                    <div class="absolute left-1/2 -top-4 w-0.5 h-4 bg-gray-300 dark:bg-gray-600 -translate-x-1/2"></div>
                    <TreeNode
                        :node="child"
                        :level="level + 1"
                        :members="members"
                        @select="emit('select', $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
