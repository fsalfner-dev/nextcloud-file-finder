<template>
    <div class="date-filter-container">
        <div>{{ label }}</div>
        <div class="date-filter-picker">
            <NcDateTimePicker 
                :id="id"
                type="date"
                :placeholder="t('filefinder','Click to select date')" 
                :model-value="modelValue" 
                @update:model-value="onUpdate" />
            <NcActions>
                <NcActionButton @click="onDelete">
                    <template #icon>
                        <Close :size="20" />
                    </template>
                    Delete
                </NcActionButton>
            </NcActions>
        </div>
    </div>
</template>

<script>
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'DateFilter',
    components: {
        NcDateTimePicker,
        NcActions,
        NcActionButton,
        Close,
    },
    props: {
        modelValue: {
            type: Date,
            default: new Date(),
        },
        dateType: {
            type: String,
            default: 'after'
        }
    },
    computed: {
        label() {
            if (this.dateType === 'after') {
                return t('filefinder', 'Only show files after');
            } else {
                return t('filefinder', 'Only show files before');
            }
        }
    },
    emits: ['update:model-value'],
    methods: {
        onUpdate(e) {
            this.$emit('update:model-value', e);
        },
        onDelete(e) {
            this.$emit('update:model-value', null);
        }
    }
};

</script>

<style scoped lang="scss">
.date-filter-container {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding-left: 20px;
    padding-bottom: 8px;
}

.date-filter-picker {
    display: flex;
    flex-direction: row;
    gap: 4px;
    align-items: center;
    justify-content: flex-start;
}
</style>
