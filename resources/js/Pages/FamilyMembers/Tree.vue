<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import TreeNode from '@/Components/FamilyTree/TreeNode.vue';
import NetworkView from '@/Components/FamilyTree/NetworkView.vue';
import FocusedFamilyView from '@/Components/FamilyTree/FocusedFamilyView.vue';

const props = defineProps({
    members: Array,
    relationshipTypes: Object,
});

const selectedMember = ref(null);
const viewMode = ref('focused'); // 'focused', 'tree', or 'network' - default to focused for usability

// Check if a member has tree-type relationships (parent/child/spouse/sibling)
const hasTreeRelationships = (member) => {
    return (member.parents?.length > 0) ||
           (member.children?.length > 0) ||
           (member.spouses?.length > 0) ||
           (member.siblings?.length > 0);
};

// Check if a member has ANY relationships (including extended like cousin)
const hasAnyRelationships = (member) => {
    return member.all_related_members?.length > 0;
};

// Find root members (those without parents but WITH tree relationships)
// Deduplicate: if two members are spouses and both have no parents, only keep one
const rootMembers = computed(() => {
    const potentialRoots = props.members.filter(m =>
        (!m.parents || m.parents.length === 0) && hasTreeRelationships(m)
    );

    // Track which members we've already included (or their spouse)
    const includedIds = new Set();
    const result = [];

    for (const member of potentialRoots) {
        if (includedIds.has(member.id)) continue;

        result.push(member);
        includedIds.add(member.id);

        // Also mark spouses as included to avoid duplicates
        if (member.spouses?.length) {
            member.spouses.forEach(spouse => includedIds.add(spouse.id));
        }
    }

    return result;
});

// Members with extended relationships only (cousin, grandparent, etc.) - not in tree
const extendedFamilyMembers = computed(() => {
    return props.members.filter(m => !hasTreeRelationships(m) && hasAnyRelationships(m));
});

// Members without any relationships at all
const orphanMembers = computed(() => {
    return props.members.filter(m => !hasAnyRelationships(m));
});

// Build tree structure
const buildTree = (member, visited = new Set()) => {
    if (visited.has(member.id)) return null;
    visited.add(member.id);

    return {
        ...member,
        childNodes: (member.children || [])
            .map(child => {
                const fullChild = props.members.find(m => m.id === child.id);
                return fullChild ? buildTree(fullChild, visited) : null;
            })
            .filter(Boolean),
        spouseNodes: (member.spouses || [])
            .map(spouse => props.members.find(m => m.id === spouse.id))
            .filter(Boolean),
    };
};

const trees = computed(() => {
    return rootMembers.value.map(root => buildTree(root)).filter(Boolean);
});

// Get generation level colors
const getGenerationColor = (level) => {
    const colors = [
        'from-amber-500 to-orange-500',
        'from-blue-500 to-indigo-500',
        'from-green-500 to-teal-500',
        'from-purple-500 to-pink-500',
        'from-red-500 to-rose-500',
    ];
    return colors[level % colors.length];
};

const selectMember = (member) => {
    selectedMember.value = member;
};
</script>

<template>
    <AppLayout title="Family Tree">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="route('family-members.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        Family Tree
                    </h2>
                </div>
                <Link
                    :href="route('family-members.create')"
                    class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-md transition"
                >
                    <svg class="w-5 h-5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Add Member
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div v-if="members.length === 0" class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No family members yet</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6">Add family members and their relationships to build your tree.</p>
                    <Link
                        :href="route('family-members.create')"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-md transition"
                    >
                        Add Your First Family Member
                    </Link>
                </div>

                <!-- Tree View -->
                <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                {{ members.length }} Family Members
                            </h3>
                            <div class="flex items-center space-x-4">
                                <!-- View Mode Toggle -->
                                <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                                    <button
                                        @click="viewMode = 'focused'"
                                        :class="[
                                            'px-3 py-1 text-sm font-medium rounded-md transition',
                                            viewMode === 'focused'
                                                ? 'bg-white dark:bg-gray-600 text-amber-600 dark:text-amber-400 shadow'
                                                : 'text-gray-600 dark:text-gray-300'
                                        ]"
                                    >
                                        Explorer
                                    </button>
                                    <button
                                        @click="viewMode = 'network'"
                                        :class="[
                                            'px-3 py-1 text-sm font-medium rounded-md transition',
                                            viewMode === 'network'
                                                ? 'bg-white dark:bg-gray-600 text-amber-600 dark:text-amber-400 shadow'
                                                : 'text-gray-600 dark:text-gray-300'
                                        ]"
                                    >
                                        Network
                                    </button>
                                    <button
                                        @click="viewMode = 'tree'"
                                        :class="[
                                            'px-3 py-1 text-sm font-medium rounded-md transition',
                                            viewMode === 'tree'
                                                ? 'bg-white dark:bg-gray-600 text-amber-600 dark:text-amber-400 shadow'
                                                : 'text-gray-600 dark:text-gray-300'
                                        ]"
                                    >
                                        Tree
                                    </button>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Click on a member to view details
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 overflow-x-auto">
                        <!-- Focused Explorer View -->
                        <div v-if="viewMode === 'focused'">
                            <FocusedFamilyView
                                :members="members"
                                :relationship-types="relationshipTypes"
                                @select="selectMember"
                            />
                        </div>

                        <!-- Network View -->
                        <div v-else-if="viewMode === 'network'">
                            <NetworkView
                                :members="members"
                                :relationship-types="relationshipTypes"
                                @select="selectMember"
                            />
                        </div>

                        <!-- Tree Visualization -->
                        <div v-else class="min-w-max">
                            <div v-for="(tree, treeIndex) in trees" :key="tree.id" class="mb-12">
                                <!-- Recursive Tree Component -->
                                <div class="tree-node">
                                    <TreeNode
                                        :node="tree"
                                        :level="0"
                                        :members="members"
                                        @select="selectMember"
                                    />
                                </div>
                            </div>

                            <!-- No tree relationships message -->
                            <div v-if="trees.length === 0 && orphanMembers.length > 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <p class="mb-2">No family tree connections yet.</p>
                                <p class="text-sm">Add parent/child/spouse relationships to build your tree.</p>
                            </div>

                            <!-- Extended family members (only extended relationships like cousin) -->
                            <div v-if="extendedFamilyMembers.length" class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Extended family (cousins, etc.):</h4>
                                <div class="flex flex-wrap gap-4">
                                    <Link
                                        v-for="member in extendedFamilyMembers"
                                        :key="member.id"
                                        :href="route('family-members.show', member.id)"
                                        class="flex items-center p-3 bg-purple-50 dark:bg-purple-900/30 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/50 transition"
                                    >
                                        <div class="w-10 h-10 rounded-full bg-purple-200 dark:bg-purple-800 flex items-center justify-center text-purple-600 dark:text-purple-300 font-medium mr-3">
                                            {{ member.first_name?.[0] }}
                                        </div>
                                        <span class="text-gray-900 dark:text-white">{{ member.display_name }}</span>
                                    </Link>
                                </div>
                            </div>

                            <!-- Orphan members (no relationships at all) -->
                            <div v-if="orphanMembers.length" class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-4">Members without relationships:</h4>
                                <div class="flex flex-wrap gap-4">
                                    <Link
                                        v-for="member in orphanMembers"
                                        :key="member.id"
                                        :href="route('family-members.show', member.id)"
                                        class="flex items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                                    >
                                        <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-medium mr-3">
                                            {{ member.first_name?.[0] }}
                                        </div>
                                        <span class="text-gray-900 dark:text-white">{{ member.display_name }}</span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Member Details (hidden in focused view since it shows inline) -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 transform translate-y-4"
                    enter-to-class="opacity-100 transform translate-y-0"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-if="selectedMember && viewMode !== 'focused'" class="fixed bottom-4 right-4 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 p-4">
                        <button
                            @click="selectedMember = null"
                            class="absolute top-2 right-2 text-gray-400 hover:text-gray-600"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center text-amber-600 dark:text-amber-400 font-bold mr-3">
                                {{ selectedMember.first_name?.[0] }}{{ selectedMember.last_name?.[0] || '' }}
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ selectedMember.display_name }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ selectedMember.full_name }}</p>
                            </div>
                        </div>
                        <div v-if="selectedMember.age" class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            {{ selectedMember.is_living ? `${selectedMember.age} years old` : `Lived ${selectedMember.age} years` }}
                        </div>
                        <Link
                            :href="route('family-members.show', selectedMember.id)"
                            class="block w-full text-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-md transition"
                        >
                            View Profile
                        </Link>
                    </div>
                </Transition>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.tree-node {
    display: flex;
    justify-content: center;
}
</style>
