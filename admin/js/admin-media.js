/*
global ajaxurl, CSS, fileQueued, jQuery, pixxioI18n, uploadSuccess, XMLSerializer
*/
( function ( $ ) {
	let pxCurrentFrame = null;

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
					text: pixxioI18n.import_from,
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

			pxCurrentFrame = this;

			const library = pxCurrentFrame.state()?.get( 'library' );
			if ( library && ! library.length ) {
				library?.more();
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

				const serializer = new XMLSerializer();
				return serializer.serializeToString( fragment );
			},
		} );
	}

	const pxFrame = wp.media.view.MediaFrame.Select.extend( {
		_pixxioExclusive: true,
	} );

	let pxFrameInstance = null;

	window.addEventListener( 'load', () => {
		const pixxioBtn = document.querySelector( '#pixxio-uploader' );

		if ( pixxioBtn ) {
			pixxioBtn.addEventListener( 'click', () => {
				if ( ! pxFrameInstance ) {
					pxFrameInstance = new pxFrame( {
						title: pixxioBtn.innerText,
					} );
				}

				pxFrameInstance.open();
				pxFrameInstance.renderPixxioFrame();
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

	function frameIsCurrent( frameEl ) {
		return (
			frameEl &&
			frameEl?.contentWindow &&
			frameEl.offsetParent &&
			( frameEl.isConnected === undefined ||
				frameEl.isConnected === true )
		);
	}

	function pxSDK() {
		if ( ! frameIsCurrent( pixxioSdk ) ) {
			pixxioSdk = Array.from(
				document.querySelectorAll( 'iframe#pixxio_sdk' )
			).filter( frameIsCurrent )?.[ 0 ];
		}

		if ( ! pixxioSdk ) {
			throw new Error( 'Could not find Pixx.io SDK iframe' );
		}

		return pixxioSdk;
	}

	function pxSend( method, parameters = [] ) {
		if ( ! Array.isArray( parameters ) ) {
			parameters = [ parameters ];
		}

		pxSDK().contentWindow.postMessage(
			{
				receiver: 'pixxio-plugin-sdk',
				method,
				parameters,
			},
			'https://plugin.pixx.io'
		);
	}

	let downloadErrors = [];
	let chunkResponseEnabled = false;

	function calculateProgress( progressData ) {
		const fileMaxPart = 100 / progressData.totalFiles;
		let progress = 0;
		progressData.fileProgress.forEach( ( percent ) => {
			// leave space for WP/PHP processing time after download
			// by using only half the value and filling the rest
			// with a fake progress value increased by an interval
			progress +=
				( ( percent / 100 ) * fileMaxPart ) /
				( chunkResponseEnabled ? 2 : 1 );
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
						if ( ! responseLines.length ) {
							return;
						}

						const lastLine =
							responseLines[ responseLines.length - 1 ].trim();
						let lastResponse = {};
						try {
							lastResponse = JSON.parse( lastLine );
						} catch ( e ) {
							return;
						}

						if ( lastResponse?.success === undefined ) {
							if ( lastResponse?.progress !== undefined ) {
								chunkResponseEnabled = true;
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
							progressData.fileProgress.set( file, 100 );
							jAjax.fileProcessed( lastResponse, ...arguments );
						}
					},
					false
				);

				return xhr;
			},
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'download_pixxio_image',
				file,
				returnMediaItem: !! mediaItems,
				nonce: pxSDK().dataset.nonce,
			},
			// we don't use the default success()
			// because otherwise it might be fired twice
			fileProcessed( data ) {
				progressData.processedFiles++;
				const allFilesFinished =
					progressData.processedFiles >= progressData.totalFiles;
				const attachmentData = data?.data;

				if ( allFilesFinished ) {
					clearInterval( progressData.fakeInterval );
					pxSend( 'setDownloadComplete' );
				} else {
					pxSend( 'setDownloadProgress', [
						calculateProgress( progressData ),
					] );
				}

				if ( data.success ) {
					if ( ! attachmentData._existed ) {
						wp.Uploader.queue.add( attachmentData );
					}

					if (
						mediaItems &&
						attachmentData._returnMediaItemUrl &&
						! mediaItems.querySelector(
							'#media-item-' + attachmentData.id
						)
					) {
						const fileObj = {
							id: attachmentData.id,
							name: attachmentData.filename,
						};
						fileQueued( fileObj );
						uploadSuccess( fileObj, attachmentData.id );
					}
				} else {
					// eslint-disable-next-line no-console
					console.error( data );
					const fileError = `${ file.fileName }: ${ attachmentData }`;
					downloadErrors.push( fileError );
					// @TODO: downloadErrors.join("\n")
					// once multiple messages are supported
					pxSend( 'showError', [ fileError ] );
				}

				if ( pxCurrentFrame ) {
					const library = pxCurrentFrame.state().get( 'library' );
					let attachment = library.get( attachmentData.id );
					if ( ! attachment ) {
						attachment = library.add( attachmentData, {
							merge: true,
							at: 0,
						} );
					}

					if ( allFilesFinished && ! downloadErrors.length ) {
						if ( pxCurrentFrame?._pixxioExclusive ) {
							pxCurrentFrame.close();
						} else {
							pxCurrentFrame?.content.mode( 'browse' );
						}
						const attElSelector = `.attachment[data-id="${ parseInt(
							attachmentData.id
						) }"]`;
						window.setTimeout( () => {
							pxCurrentFrame.content
								.get()
								?.$el.get( 0 )
								?.querySelector( attElSelector )
								?.scrollIntoView();
						}, 1 );
					} else if ( downloadErrors.length ) {
						pxCurrentFrame?.state()?.get( 'selection' )?.reset();
					}

					pxCurrentFrame
						?.state()
						?.get( 'selection' )
						.add( attachment, {
							merge: true,
						} );
				}
			},
		} );
	}

	function downloadFiles( files ) {
		if ( ! files || ! files.length ) {
			console.warn( 'No files to download' ); // eslint-disable-line no-console
		}

		downloadErrors = [];

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
	if ( ! CSS.supports( 'selector(:has(+ *))' ) ) {
		const pxMedia = document.querySelectorAll( '.media-icon img.pixxio' );
		if ( pxMedia && pxMedia.length ) {
			pxMedia.forEach( ( el ) => {
				el.closest( '.media-icon' ).classList.add( 'pixxio' );
			} );
		}
	}
} )( jQuery );
