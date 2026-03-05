<template>
    <NcPopover
        :shown="showPopover"
        @update:shown="updateShow"
        popupRole="menu">
        <template #trigger>
            <NcButton 
                :aria-label="explanation"
                :text="explanation"
                size="small"
                variant="tertiary"
                :disabled="!isRootDir(filePath)">
                <template #icon>
                    <slot name="icon"></slot>
                </template>
            </NcButton>
        </template>
        <template #default>
            <div class="folder-action-popover">
                <div class="folder-action-popover-heading">
                    {{ explanation }}
                </div>
                <ul>
                    <NcListItem v-for="folder in paths"
                        compact
                        :name="folder"
                        @click="onSelect(folder)">
                        <template #icon>
                            <slot name="icon"></slot>
                        </template>
                    </NcListItem>
                </ul>
            </div>
        </template>
    </NcPopover>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcListItem from '@nextcloud/vue/components/NcListItem'


export default {
    name: 'FolderAction',
    components: {
        NcPopover,
        NcButton,
        NcListItem,
    },
    props: {
        filePath: {
            type: String,
            default: ''
        },
        explanation: {
            type: String,
            default: ''
        }
    },
    emits: ['folderAction'],
    data() {
        return {
            showPopover: false,
        }
    },
    computed: {
        paths() {
            // filePath has the form "Root/Dir1/Dir2/filename.ext"
            // @returns ["Root/", "Root/Dir1/", "Root/Dir1/Dir2/"]
            const segments = this.filePath.split('/');

            // remove the filename part
            segments.pop(); 

            const output = segments.map((_, index) => 
                segments.slice(0, index + 1).join('/') + '/');
            return output;
        }
    }, 
    methods: {
        isRootDir(path) {
            return path.includes('/');
        },
        onSelect(path) {
            this.updateShow(false);
            this.$emit('folderAction', path);
        },
        updateShow(event) {
            this.showPopover = event;
        },
    }
};

</script>

<style scoped lang="scss">

.folder-action-popover {
    width: 300px;
    padding: 2px 15px 2px 5px;
}

.folder-action-popover-heading {
    font-weight: bold;
    padding: 5px 15px 0px 5px;
}

</style>
