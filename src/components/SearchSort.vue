<template>
    <div class="search-sort">
        <label for="sort-select">Sort by:</label>
        <select id="sort-select" v-model="selectedSort" @change="updateSort">
            <option value="score">Content match score</option>
            <option value="modified">Modification date</option>
            <option value="path">File path</option>
        </select>
    </div>
</template>

<script>
export default {
    name: 'SearchSort',
    props: {
        modelValue: {
            type: String,
            default: 'score'
        },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            selectedSort: this.modelValue,
        }
    },
    watch: {
        modelValue(newValue) {
            this.selectedSort = newValue;
        }
    },
    methods: {
        updateSort() {
            this.$emit('update:modelValue', this.selectedSort);
        },
    },
}
</script>

<style scoped>
.search-sort {
    display: flex;
    align-items: center;
    gap: 8px;
}

#sort-select {
    padding: 4px 8px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background-color: var(--color-main-background);
    color: var(--color-main-text);
    font-size: 14px;
    cursor: pointer;
}

#sort-select:hover {
    border-color: var(--color-primary-element);
}

#sort-select:focus {
    outline: none;
    border-color: var(--color-primary-element);
}
</style>
