<template>
    <div class="search-filename">
        <NcTextField v-model="search_filename_text"
            label="Filename. This may contain wildcards."
            trailing-button-icon="close"
            :show-trailing-button="search_filename_text !== ''"
            @trailing-button-click="clearText">
            <template #icon>
                <Magnify :size="20" />
            </template>
        </NcTextField>    
    </div>
</template>

<script>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
    name: 'SearchFilename',
    data() {
        return {
            search_filename_text: ''
        }
    },
	components: {
		NcTextField,
        Magnify,
        Close
    },
	methods: {
		clearText() {
			this.search_filename_text = ''
		}
	}
}
</script>

<style scoped>
.search-filename {
    width: 100%;
    display: flex;
    justify-content: center;
    padding: 4px 32px;
}

.search-input {
    width: 80%;
    padding: 8px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
</style>