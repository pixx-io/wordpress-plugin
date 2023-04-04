( function ( $ ) {
	const commonFrame = {
		initialize() {
			const pxState = wp.media.controller.State.extend( {
				insert() {
					this.frame.close();
				},
			} );

			this.states.add( [
				new pxState( {
					id: 'pixxio',
					search: false,
					title: 'pixx.io',
				} ),
			] );
		},
		bindHandlers() {
			this.on( 'content:create', this.initCheck, this );

			this.on( 'content:render:pixxio', this.renderPixxioFrame, this );
		},
		browseRouter( routerView ) {
			routerView.set( {
				pixxio: {
					text: 'Import from pixx.io',
					priority: 30,
				},
			} );
		},
		renderPixxioFrame() {
			const pixxioContent = wp.Backbone.View.extend( {
				tagName: 'div',
				className: 'pixxio-content',
				template: wp.template( 'pixxio-content' ),
				active: false,
				toolbar: null,
				frame: null,
			} );

			const view = new pixxioContent();

			this.content.set( view );

			if ( ! pixxioSdk ) {
				pixxioSdk = this.el.querySelector( 'iframe#pixxio_sdk' );
			} else {
				this.el
					.querySelector( 'iframe#pixxio_sdk' )
					?.replaceWith( pixxioSdk );
			}
		},
	};
	// add class in Library view
	wp.media.view.Attachment.Library = wp.media.view.Attachment.Library.extend(
		{
			className() {
				let parentClassName =
					wp.media.view.Attachment.Library.__super__.className;

				if (
					! parentClassName ||
					typeof parentClassName === 'function'
				) {
					parentClassName = 'attachment';
				}

				let className = parentClassName;
				const fromPixxio = this.model.get( 'pixxio_id' ) !== undefined;
				if ( fromPixxio ) {
					className += ' pixxio';
				}

				return className;
			},
		}
	);

	const frame = wp.media.view.MediaFrame.Post;
	const select = wp.media.view.MediaFrame.Select;
	let pixxioSdk = null;
	const mediaItems = document.querySelector( '#media-items' );

	wp.media.view.MediaFrame.Post = frame.extend( {
		initialize() {
			frame.prototype.initialize.apply( this, arguments );
			commonFrame.initialize.apply( this, arguments );
		},
		bindHandlers() {
			frame.prototype.bindHandlers.apply( this, arguments );
			commonFrame.bindHandlers.apply( this, arguments );
		},
		browseRouter() {
			commonFrame.browseRouter.apply( this, arguments );
			frame.prototype.browseRouter.apply( this, arguments );
		},
		renderPixxioFrame: commonFrame.renderPixxioFrame,
	} );

	wp.media.view.MediaFrame.Select = select.extend( {
		initCheck: commonFrame.initCheck,
		initialize() {
			select.prototype.initialize.apply( this, arguments );
			commonFrame.initialize.apply( this, arguments );
		},
		bindHandlers() {
			select.prototype.bindHandlers.apply( this, arguments );
			commonFrame.bindHandlers.apply( this, arguments );
		},
		browseRouter() {
			commonFrame.browseRouter.apply( this, arguments );
			if ( ! this._pixxioExclusive ) {
				select.prototype.browseRouter.apply( this, arguments );
			}
		},
		renderPixxioFrame: commonFrame.renderPixxioFrame,
	} );

	const pxFrame = wp.media.view.MediaFrame.Select.extend( {
		_pixxioExclusive: true,
	} );

	window.addEventListener( 'load', () => {
		const pixxioBtn = document.querySelector( '#pixxio-uploader' );

		if ( pixxioBtn ) {
			pixxioBtn.addEventListener( 'click', () => {
				wp.media.frame = new pxFrame( { title: pixxioBtn.innerText } );
				wp.media.frame.open();
				wp.media.frame.renderPixxioFrame();
			} );

			const plBtn = document.querySelector( '#plupload-browse-button' );
			if ( plBtn && pixxioBtn ) {
				plBtn.after( pixxioBtn );
				pixxioBtn.classList.remove( 'button-hero' );
				const ddArea = pixxioBtn.closest( '#drag-drop-area' );
				ddArea.style.height = 'auto';
			}
		}
	} );

	function pxSend( method, parameters = [] ) {
		pixxioSdk.contentWindow.postMessage(
			{
				receiver: 'pixxio-plugin-sdk',
				method,
				parameters,
			},
			'https://plugin.pixx.io'
		);
	}

	window.addEventListener( 'message', ( messageEvent ) => {
		if (
			messageEvent?.origin !== 'https://plugin.pixx.io' ||
			messageEvent?.data?.sender !== 'pixxio-plugin-sdk'
		)
			return;

		if ( messageEvent?.data?.method === 'downloadFiles' ) {
			$.ajax( {
				// @TODO: actually handle progress or remove
				xhr() {
					const xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener(
						'progress',
						function ( event ) {
							// console.log( event );
							if ( event.lengthComputable ) {
								// const percentComplete =
								// 	event.loaded / event.total;
								//Do something with upload progress here
							}
						},
						false
					);

					xhr.addEventListener(
						'progress',
						function ( event ) {
							if ( event.lengthComputable ) {
								// const percentComplete =
								// 	event.loaded / event.total;
								//Do something with download progress
							}
						},
						false
					);

					return xhr;
				},
				type: 'POST',
				url: ajaxurl, // eslint-disable-line no-undef
				data: {
					action: 'download_pixxio_image',
					files: messageEvent?.data?.parameters,
					returnMediaItem: !! mediaItems,
				},
				success( data ) {
					//Do something on success
					if ( data.success ) {
						pxSend( 'setDownloadComplete' );
						wp.Uploader.queue.add( data.data );
						if (
							mediaItems &&
							data.data._returnMediaItemUrl &&
							! mediaItems.querySelector(
								'#media-item-' + data.data.id
							)
						) {
							const fileObj = {
								id: data.data.id,
								name: data.data.filename,
							};
							fileQueued( fileObj ); // eslint-disable-line no-undef
							uploadSuccess( fileObj, data.data.id ); // eslint-disable-line no-undef
						}
					}
				},
			} );
			// pxSend( 'setDownloadProgress', [0] );
		}
		// window.setTimeout( () => pxSend( 'setDownloadComplete' ), 1000 );
	} );
} )( jQuery ); // eslint-disable-line no-undef
