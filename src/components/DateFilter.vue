<template>
    <div class="date-filter-picker">
        <NcDateTimePicker 
            type="date"
            :placeholder="placeholder" 
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
</template>

<script>
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import IconPlus from 'vue-material-design-icons/Plus.vue'
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
        placeholder: {
            type: String,
            default: ''
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
.date-filter-picker {
    display: flex;
    flex-direction: row;
    gap: 4px;
    align-items: center;
    justify-content: flex-start;
    padding-left: 16px;
}
</style>
