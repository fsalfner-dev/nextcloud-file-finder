<template>
    <NcPopover
        :shown="showPopover"
        @update:shown="updateShow"
        popupRole="menu">
        <template #trigger>
            <NcButton 
                aria-label="Exclude paths"
                text="Exclude paths"
                size="small"
                variant="tertiary"
                :disabled="!isRootDir(filePath)">
                <template #icon>
                    <IconFolderCancelOutline :size="15" />
                </template>
            </NcButton>
        </template>
        <template #default>
            <div class="exclude-folder-popover">
                <div class="exclude-folder-popover-heading">
                    Exclude all files and folders under ...
                </div>
                <ul>
                    <NcListItem v-for="folder in paths"
                        compact
                        :name="folder"
                        @click="onSelect(folder)">
                        <template #icon>
                            <IconFolderCancelOutline :size="15" />
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
import IconFolderCancelOutline from 'vue-material-design-icons/FolderCancelOutline.vue'


export default {
    name: 'ExcludeFolderAction',
    components: {
        NcPopover,
        NcButton,
        IconFolderCancelOutline,
        NcListItem,
    },
    props: {
        filePath: {
            type: String,
            default: ''
        }
    },
    emits: ['excludeFolder'],
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
            this.$emit('excludeFolder', path);
        },
        updateShow(event) {
            this.showPopover = event;
        },
    }
};

</script>

<style scoped lang="scss">

.exclude-folder-popover {
    width: 300px;
    padding: 2px 15px 2px 5px;
}

.exclude-folder-popover-heading {
    font-weight: bold;
    padding: 5px 15px 0px 5px;
}

</style>
