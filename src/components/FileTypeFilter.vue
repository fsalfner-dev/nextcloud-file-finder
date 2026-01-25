<template>
    <div class="file-type-filter">
        <span class="file-type-filter__label">{{ t('filefinder', 'File type') }}</span>
        <div class="file-type-filter__options">
            <label v-for="opt in options" :key="opt.value" class="file-type-filter__option">
                <input
                    type="checkbox"
                    :value="opt.value"
                    :checked="selectedSet.has(opt.value)"
                    @change="toggle(opt.value)"
                >
                <span>{{ opt.label }}</span>
            </label>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FileTypeFilter',
    props: {
        modelValue: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            options: [
                { value: 'images', label: 'Images' },
                { value: 'music', label: 'Music' },
                { value: 'pdfs', label: 'PDFs' },
                { value: 'spreadsheets', label: 'Spreadsheets' },
                { value: 'documents', label: 'Documents' },
                { value: 'videos', label: 'Videos' },
            ],
        };
    },
    computed: {
        selectedSet() {
            return new Set(this.modelValue || []);
        },
        selected() {
            return this.modelValue || [];
        },
    },
    methods: {
        toggle(value) {
            const next = this.selectedSet.has(value)
                ? this.selected.filter((v) => v !== value)
                : [...this.selected, value];
            this.$emit('update:modelValue', next);
        },
    },
};
</script>

<style scoped lang="scss">
.file-type-filter {
    width: 100%;
    padding: 4px 8px;
    display: flex;
    flex-direction: column;
    gap: 0px;

    &__label {
        font-weight: 600;
        font-size: 13px;
        color: var(--color-main-text);
    }

    &__options {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    &__option {
        display: flex;
        align-items: center;
        gap: 2px;
        cursor: pointer;
        font-size: 13px;
        color: var(--color-main-text);

        input[type="checkbox"] {
            cursor: pointer;
        }
    }

    &__hint {
        font-size: 11px;
        color: var(--color-text-maxcontrast);
        margin: 0;
    }
}
</style>
