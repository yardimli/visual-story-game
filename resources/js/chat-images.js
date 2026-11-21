export function chatImages(options) {
    return {
        chatImageIds: [],
        chatImageAssets: [],
        chatImageCategories: {},
        chatImageCategory: '',
        chatImagesLoading: false,
        chatImagesError: '',
        filteredChatImages() {
            return this.chatImageAssets.filter(
                (image) => !this.chatImageCategory || image.category === this.chatImageCategory,
            );
        },
        async openChatImages() {
            this.$refs.chatImagesDialog.showModal();
            this.chatImagesError = '';
            if (!options.imagesUrl) return;
            this.chatImagesLoading = true;
            try {
                const response = await fetch(options.imagesUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Could not load project images. Please try again.');
                const data = await response.json();
                this.chatImageAssets = data.assets;
                this.chatImageCategories = data.image_categories || {};
                this.chatImageIds = this.chatImageIds.filter((id) =>
                    data.assets.some((image) => String(image.id) === String(id)),
                );
            } catch (error) {
                this.chatImagesError = error.message;
            } finally {
                this.chatImagesLoading = false;
            }
        },
    };
}
