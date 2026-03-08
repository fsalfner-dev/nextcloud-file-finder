<!--
This component renders a list of document types where each type can be selected using a checkbox.

The values of the options are translated in the backend into file extensions.

```vue
<template>
    <FileTypeFilter 
        :modelValue="fileTypes" 
        @update:model-value="onFileTypeSelect" />
</template>
```
-->

<template>
    <div>
        <NcAppNavigationList>
            <NcCheckboxRadioSwitch 
                v-for="option in options"
                :model-value="modelValue" 
                name="file-type-selection" 
                :value="option.value" 
                @update:model-value="onUpdate">
                    {{ option.label }}
            </NcCheckboxRadioSwitch>
        </NcAppNavigationList>
    </div>
</template>

<script>
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcAppNavigationList from '@nextcloud/vue/components/NcAppNavigationList'

export default {
    name: 'FileTypeFilter',
    components: {
        NcCheckboxRadioSwitch,
        NcAppNavigationList,
    },
    props: {
        /**
         * An array of selected file types.
         * The elements are option values @see options
         */
        modelValue: {
            type: Array,
            default: [],
        }
    },
    emits: ['update:model-value'],
    data() {
        return {
            options: [
                { value: 'images', label: t('filefinder','Images') },
                { value: 'music', label: t('filefinder','Music') },
                { value: 'pdfs', label: t('filefinder','PDFs') },
                { value: 'spreadsheets', label: t('filefinder','Spreadsheets') },
                { value: 'documents', label: t('filefinder','Documents') },
                { value: 'presentations', label: t('filefinder','Presentations') },
                { value: 'videos', label: t('filefinder','Videos') },
            ],
        };
    },
    methods: {
        onUpdate(e) {
            this.$emit('update:model-value', e);
        }
    }
};

</script>

<style scoped lang="scss">
</style>
