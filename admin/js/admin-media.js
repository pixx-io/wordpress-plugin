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
	const attachmentDetails = wp.media.view.Attachment.Details.TwoColumn;
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
		initialize() {
			select.prototype.initialize.apply( this, arguments );
			commonFrame.initialize.apply( this, arguments );
		},
		bindHandlers() {
			// we don't need the default handlers in the "import from pixx.io" modal
			if ( ! this._pixxioExclusive ) {
				select.prototype.bindHandlers.apply( this, arguments );
			}
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

	if ( attachmentDetails ) {
		wp.media.view.Attachment.Details.TwoColumn = attachmentDetails.extend( {
			template( attachmentData ) {
				const superTemplate =
					attachmentDetails.prototype.template.apply(
						this,
						arguments
					);
				if ( typeof attachmentData.pixxio_id === 'undefined' ) {
					return superTemplate;
				}

				const fragment = document
					.createRange()
					.createContextualFragment( superTemplate );
				const compat = fragment.querySelector( 'div.compat-meta' );

				const div = document.createElement( 'div' );
				div.className = 'pixxio-meta';

				const metaFragment = document
					.createRange()
					.createContextualFragment(
						wp.template( 'pixxio-meta' )( attachmentData )
					);

				compat.before( metaFragment );

				const serializer = new XMLSerializer(); // eslint-disable-line no-undef
				return serializer.serializeToString( fragment );
			},
		} );
	}

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
		if ( ! pixxioSdk || ! pixxioSdk?.contentWindow ) {
			pixxioSdk = document.querySelector( 'iframe#pixxio_sdk' );
		}

		if ( ! Array.isArray( parameters ) ) {
			parameters = [ parameters ];
		}

		pixxioSdk.contentWindow.postMessage(
			{
				receiver: 'pixxio-plugin-sdk',
				method,
				parameters,
			},
			'https://plugin.pixx.io'
		);
	}

	function calculateProgress( progressData ) {
		const fileMaxPart = 100 / progressData.totalFiles;
		let progress = 0;
		progressData.fileProgress.forEach( ( percent, file ) => {
			// leave space for WP/PHP processing time after download
			// by using only half the value and filling the rest
			// with a fake progress value increased by an interval
			progress += ( ( percent / 100 ) * fileMaxPart ) / 2;
		} );

		return Math.floor( progress + progressData.fakeProgress );
	}

	function downloadSingleFile( file, progressData ) {
		$.ajax( {
			xhr() {
				const xhr = new window.XMLHttpRequest();
				const jAjax = this;

				xhr.addEventListener(
					'progress',
					function ( event ) {
						const responseLines = event.currentTarget.responseText
							.split( '\n' )
							.filter( Boolean );
						const lastResponse = JSON.parse(
							responseLines[ responseLines.length - 1 ]
						);
						if ( lastResponse?.success === undefined ) {
							if ( lastResponse?.progress !== undefined ) {
								progressData.fileProgress.set(
									file,
									lastResponse.progress
								);

								pxSend( 'setDownloadProgress', [
									calculateProgress( progressData ),
								] );
								if (
									lastResponse.progress === 100 &&
									progressData.fakeInterval === null
								) {
									console.log( 'set interval' );
									progressData.fakeInterval = setInterval(
										function () {
											const progressSoFar =
												calculateProgress(
													progressData
												);

											progressData.fakeProgress +=
												( 100 - progressSoFar ) /
												( progressData.totalFiles -
													progressData.processedFiles ) /
												5;
											console.log(
												'fakeProgress',
												progressData.fakeProgress
											);

											pxSend( 'setDownloadProgress', [
												calculateProgress(
													progressData
												),
											] );
										},
										500
									);
								}
							} else {
								// eslint-disable-next-line no-console
								console.error( `Unexpected response: ${ lastResponse }` ); // prettier-ignore
							}
						} else {
							jAjax.success( lastResponse );
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
				file: file,
				returnMediaItem: !! mediaItems,
			},
			success( data ) {
				progressData.processedFiles++;
				if ( data.success ) {
					if (
						progressData.processedFiles >= progressData.totalFiles
					) {
						clearInterval( progressData.fakeInterval );
						pxSend( 'setDownloadComplete' );
					}
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
	}

	function downloadFiles( files ) {
		if ( ! files || ! files.length ) {
			console.warn( 'No files to download' ); // eslint-disable-line no-console
		}

		const progressData = {
			totalFiles: files.length,
			processedFiles: 0,
			fileProgress: new Map(),
			fakeInterval: null,
			fakeProgress: 0,
		};

		files.forEach( ( file ) => {
			progressData.fileProgress.set( file, 0 );
			downloadSingleFile( file, progressData );
		} );
	}

	window.addEventListener( 'message', ( messageEvent ) => {
		if (
			messageEvent?.origin !== 'https://plugin.pixx.io' ||
			messageEvent?.data?.sender !== 'pixxio-plugin-sdk'
		)
			return;

		if ( messageEvent?.data?.method === 'downloadFiles' ) {
			downloadFiles( messageEvent?.data?.parameters[ 0 ] );
		}
	} );

	// add pixxio icon to classic table view in browsers without :has() support
	if (
		! CSS.supports( 'selector(:has(+ *))' ) // eslint-disable-line no-undef
	) {
		const pxMedia = document.querySelectorAll( '.media-icon img.pixxio' );
		if ( pxMedia && pxMedia.length ) {
			pxMedia.forEach( ( el ) => {
				el.closest( '.media-icon' ).classList.add( 'pixxio' );
			} );
		}
	}
} )( jQuery ); // eslint-disable-line no-undef
