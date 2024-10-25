/**
 * Included when youtube_uploader fields are rendered for editing by publishers.
 */
 ( function( $ ) {
	class YTUploader {
		/**
		 * $field is a jQuery object wrapping field elements in the editor.
		 */
        constructor($field) {
			this.field = $field;
            this.postId = acf.get('post_id');
			this.postStatus = window[this.field.data('type') + '_obj'].postStatus;

            this.init();
        }

		init() {
			this.wrapper = document.querySelector('.' + this.field.data('key') + '__wrapper');
			if(this.wrapper) {
				this.hiddenValueInput = this.wrapper.querySelector('.' + this.field.data('key') + '__hidden_value_input');
				this.hiddenModeInput = this.wrapper.querySelector('.' + this.field.data('key') + '__hidden_mode_input');
				this.tabs = this.wrapper.querySelector('.' + this.field.data('key') + '__tabs');
				this.fileInput = this.wrapper.querySelector('.' + this.field.data('key') + '__file_input');
				this.button = this.wrapper.querySelector('.' + this.field.data('key') + '__button');
				this.playlistSelect = this.wrapper.querySelector('.' + this.field.data('key') + '__playlist_select');
				this.videoSelect = this.wrapper.querySelector('.' + this.field.data('key') + '__video_select');
				this.responseDiv = this.wrapper.querySelector('.' + this.field.data('key') + '__response');
				this.spinner = this.wrapper.querySelector('.' + this.field.data('key') + '__spinner');

				this.activeTab = localStorage.getItem(this.field.data('key') + '__active_tab') || 0;
				this.playlistId = localStorage.getItem(this.field.data('key') + '_' + this.postId + '__playlist_id') || '';
				this.videoId = localStorage.getItem(this.field.data('key') + '_' + this.postId + '__video_id') || '';

				this.initTabs();
				this.initUpload();
				this.initSelect();
			};
		}

		initTabs() {
			if(this.tabs) {
				$(this.tabs).tabs({
					active: parseInt(this.activeTab),
					activate: (event, ui) => {
						localStorage.setItem(this.field.data('key') + '__active_tab', ui.newTab.index());
					},
				});
			}
		}

		initUpload() {
			if(this.button) {
				this.button.addEventListener('click', (event) => {
					event.preventDefault();

					let title = '';
					let excerpt = '';
					const file = this.fileInput && this.fileInput.files.length > 0 ? this.fileInput.files[0] : null;

					const isGutenberg = !!window.wp && !!window.wp.data;

					if (isGutenberg) {
						//https://wordpress.stackexchange.com/a/351788/99214
						const { select } = wp.data;
                		title = select('core/editor').getEditedPostAttribute('title') || '';
                		excerpt = select('core/editor').getEditedPostAttribute('excerpt') || '';
					} else {
						title = document.getElementById('title') ? document.getElementById('title').value.trim() : '';
						excerpt = document.getElementById('excerpt') ? document.getElementById('excerpt').value.trim() : '';
					}

					if (!title /*|| !excerpt*/ || !file) {
						let message = acf._e(this.field.data('type'), 'before_uploading');
						if (!title) message += '\n- ' + acf._e(this.field.data('type'), 'enter_title');
						//if (!excerpt) message += '\n- ' + acf._e(this.field.data('type'), 'enter_description');
						if (!file) message += '\n- ' + acf._e(this.field.data('type'), 'select_video_file');
						this.thickbox(message);
						return;
					}
				
					this.responseDiv.textContent = acf._e(this.field.data('type'), 'preparing_upload') + '...';
					this.button.disabled = true;
					this.spinner.style.display = 'block';

					const formData = new FormData();
					formData.append( 'action', 'get_youtube_upload_url' );
					formData.append( 'post_id', this.postId );
					formData.append( 'field_key', this.field.data('key') );

					fetch(ajaxurl, {
						method: 'POST',
						body: formData,
					})
						.then(response => {
							const contentType = response.headers.get("content-type");
							if (contentType && contentType.indexOf("application/json") !== -1) {
								return response.json();
							} else {
								return response.text().then(text => ({
									success: false,
									data: {
										code: response.status,
										message: this.getResponseMessage(text)
									}
								}));
							}
						})
						.then(response => {
							const message = this.getResponseMessage(response);
							if (!response.success) {
								throw new Error(message);
							}

							this.upload(file, response.data.upload_url);
						})
						.catch(error => {
							this.responseDiv.textContent = this.getResponseMessage(error);
							this.button.disabled = false;
						})
						.finally(() => {
							this.spinner.style.display = 'none';
						});
				});
			}
		}

		initSelect() {
			if(this.playlistSelect && this.videoSelect) {
				this.videoSelect.disabled = true;

				if (this.playlistId) {
					this.playlistSelect.value = this.playlistId;
				}

				this.loadVideosByPlaylist();

				this.playlistSelect.addEventListener('change', (event) => {
					this.playlistId = this.playlistSelect.value;
					this.videoId = '';

					this.hiddenValueInput.value = this.videoId;
					this.hiddenModeInput.value = '';
					this.videoSelect.innerHTML = '';
					this.videoSelect.disabled = true;

					localStorage.setItem(this.field.data('key') + '_' + this.postId + '__playlist_id', this.playlistId);
					localStorage.setItem(this.field.data('key') + '_' + this.postId + '__video_id', this.videoId);

					this.loadVideosByPlaylist();
				});

				this.videoSelect.addEventListener('change', (event) => {
					this.videoId = this.videoSelect.value;

					this.hiddenValueInput.value = this.videoId;

					localStorage.setItem(this.field.data('key') + '_' + this.postId + '__video_id', this.videoId);
				});
			}
		}

		loadVideosByPlaylist() {
			if (this.playlistId) {
				this.responseDiv.textContent = acf._e(this.field.data('type'), 'wait_please') + '...';
				this.spinner.style.display = 'block';

				const formData = new FormData();
				formData.append('action', 'get_videos_by_playlist');
				formData.append('field_key', this.field.data('key'));
				formData.append('playlist_id', this.playlistId);

				fetch(ajaxurl, {
					method: 'POST',
					body: formData,
				})
					.then(response => {
						const contentType = response.headers.get("content-type");
						if (contentType && contentType.indexOf("application/json") !== -1) {
							return response.json();
						} else {
							return response.text().then(text => ({
								success: false,
								data: {
									code: response.status,
									message: this.getResponseMessage(text)
								}
							}));
						}
					})
					.then(response => {
						const message = this.getResponseMessage(response);
						if (!response.success) {
							throw new Error(message);
						}

						let defaultOption = document.createElement('option');
						defaultOption.value = '';
						defaultOption.text = '- ' + acf._e(this.field.data('type'), 'select') + ' -';
						this.videoSelect.appendChild(defaultOption);

						response.data.items.forEach(item => {
							let option = document.createElement('option');
							option.value = item.id;
							option.text = item.title + ' (' + item.id + ')';
							this.videoSelect.appendChild(option);
						});

						if (this.videoId) {
							this.videoSelect.value = this.videoId;
							this.hiddenValueInput.value = this.videoId;
						}

						this.responseDiv.textContent = '';
						this.videoSelect.disabled = false;
					})
					.catch(error => {
						this.responseDiv.textContent = this.getResponseMessage(error);
					})
					.finally(() => {
						this.spinner.style.display = 'none';
					});
			}
		}

		upload(file, uploadUrl) {
			const xhr = new XMLHttpRequest();
		
			xhr.upload.addEventListener('progress', (event) => {
				if (event.lengthComputable) {
					const percentComplete = (event.loaded / event.total) * 100;
					this.responseDiv.textContent = acf._e(this.field.data('type'), 'loading') + ': ' + Math.round(percentComplete) + '%';
				}
			}, false);
		
			xhr.addEventListener('load', (event) => {
				if (xhr.status >= 200 && xhr.status < 300) {
					const response = JSON.parse(xhr.responseText);

					this.responseDiv.textContent = acf._e(this.field.data('type'), 'video_uploaded_successfully');
					this.hiddenValueInput.value = response.id;
					this.hiddenModeInput.value = 'upload';

					this.save(response.id);
				} else {
					this.responseDiv.textContent = acf._e(this.field.data('type'), 'error_while_uploading');
					this.button.disabled = false;
				}
			});
		
			xhr.addEventListener('error', (event) => {
				this.responseDiv.textContent = acf._e(this.field.data('type'), 'network_error_while_uploading');
				this.button.disabled = false;
			});

			xhr.open('PUT', uploadUrl, true);
    		xhr.setRequestHeader('Content-Type', 'application/octet-stream');
    		xhr.send(file);
		}

		save(videoId) {
			const formData = new FormData();
			formData.append('action', 'save_youtube_video_id');
			formData.append('post_id', this.postId);
			formData.append('field_key', this.field.data('key'));
			formData.append('video_id', videoId);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData,
			})
				.then(response => {
					const contentType = response.headers.get("content-type");
					if (contentType && contentType.indexOf("application/json") !== -1) {
						return response.json();
					} else {
						return response.text().then(text => ({
							success: false,
							data: {
								code: response.status,
								message: this.getResponseMessage(text)
							}
						}));
					}
				})
				.then(response => {
					const message = this.getResponseMessage(response);
					if (!response.success) {
						throw new Error(message);
					}
				})
				.catch(error => {
					let message = acf._e(this.field.data('type'), 'following_error');
    	    	    message += '\n"' + this.getResponseMessage(error) + '"';
    	    	    message += '\n' + acf._e(this.field.data('type'), 'recommended_save_post');
    	    	    this.thickbox(message);
				});
		}

		thickbox(message) {
			const uniqueId = 'thickbox_' + Date.now();
			const formattedMessage = message.replace(/\n/g, '<br>');
    	    const content = `
    	        <div id="${uniqueId}" style="display:none;">
    	            <p>${formattedMessage}</p>
    	        </div>
    	    `;

    	    document.body.insertAdjacentHTML('beforeend', content);

			//FIXME - https://core.trac.wordpress.org/ticket/27473
    	    //tb_show(acf._e(this.field.data('type'), 'attention'), '#TB_inline?&width=300&height=100&inlineId=youtube-upload-message');
			tb_show(acf._e(this.field.data('type'), 'attention'), `#TB_inline?inlineId=${uniqueId}`);

    	    // Set initial dimensions
    	    setTimeout(this.adjustThickboxSize, 0);

    	    // Add a listener for window resizing
    	    const resizeListener = () => {
    	        this.adjustThickboxSize();
    	    };
    	    window.addEventListener('resize', resizeListener);

    	    // Remove content and listener after ThickBox is closed
    	    const observer = new MutationObserver((mutations) => {
    	        mutations.forEach((mutation) => {
    	            if (mutation.type === 'childList' && mutation.removedNodes.length > 0) {
    	                for (let node of mutation.removedNodes) {
    	                    if (node.id === 'TB_window') {
    	                        document.getElementById(uniqueId).remove();
    	                        window.removeEventListener('resize', resizeListener);
    	                        observer.disconnect();
    	                        break;
    	                    }
    	                }
    	            }
    	        });
    	    });

    	    observer.observe(document.body, {
    	        childList: true
    	    });
    	}

		adjustThickboxSize() {
			const thickboxWindow = document.getElementById('TB_window');
			if (thickboxWindow) {
				thickboxWindow.style.width = '300px';
				thickboxWindow.style.height = 'auto';
				thickboxWindow.style.marginLeft = '-150px';
				thickboxWindow.style.left = '50%'; // Center horizontally
			
				const thickboxContent = document.getElementById('TB_ajaxContent');
				if (thickboxContent) {
					thickboxContent.style.width = 'auto';
					thickboxContent.style.height = 'auto';
				}

				// Center vertically
				const windowHeight = window.innerHeight;
				const thickboxHeight = thickboxWindow.offsetHeight;
				thickboxWindow.style.top = Math.max(0, (windowHeight - thickboxHeight) / 2) + 'px';
			}
		}

		getResponseMessage(data) {
			if (data && data.data && typeof data.data.message !== 'undefined') {
				return data.data.message;
			} else if (data && typeof data.message !== 'undefined') {
				return data.message;
			} else {
				return data || acf._e(this.field.data('type'), 'technical_problem');
			}
		}
	}

	if( typeof acf.add_action !== 'undefined' ) {
		/**
		 * Run initialize_field when existing fields of this type load,
		 * or when new fields are appended via repeaters or similar.
		 */
		acf.add_action( 'ready_field/type=youtube_uploader', function($field) {
            new YTUploader($field);
        });
		acf.add_action( 'append_field/type=youtube_uploader', function($field) {
            new YTUploader($field);
        });
	}
} )( jQuery );
