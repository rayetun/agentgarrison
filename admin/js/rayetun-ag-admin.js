/* global rayetunAgData, Chart */
( function ( $ ) {
	'use strict';

	var bw = rayetunAgData || {};

	// ── Helpers ──────────────────────────────────────────────────────────────
	function bwPost( action, data, success, error ) {
		$.post( bw.ajaxUrl, $.extend( { action: action, nonce: bw.nonce }, data ), function ( res ) {
			if ( res.success ) {
				success( res.data );
			} else {
				if ( error ) { error( res.data ); }
			}
		} ).fail( function () {
			if ( error ) { error( { message: bw.strings.error } ); }
		} );
	}

	function showStatus( $el, msg, isError ) {
		$el.text( msg ).css( 'color', isError ? '#C62828' : '#2E7D32' );
		setTimeout( function () { $el.text( '' ); }, 3500 );
	}

	// ── Module toggles (Settings page) ───────────────────────────────────────
	$( document ).on( 'change', '.js-toggle-module', function () {
		var $input  = $( this );
		var module  = $input.data( 'module' );
		var enabled = $input.is( ':checked' );

		$input.closest( '.agentgarrison-module-card' )
			.toggleClass( 'is-enabled', enabled )
			.toggleClass( 'is-disabled', ! enabled );

		$input.prop( 'disabled', true );

		bwPost( 'rayetun_ag_toggle_module', { module_id: module, enabled: enabled ? 1 : 0 }, function () {
			// Reload so the sidebar navigation reflects the new module state.
			window.location.reload();
		}, function () {
			$input.prop( 'disabled', false ).prop( 'checked', ! enabled );
		} );
	} );

	// ── General settings ─────────────────────────────────────────────────────
	$( document ).on( 'click', '.js-save-general-settings', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( bw.strings.loading );
		bwPost( 'rayetun_ag_save_general_settings', {
			alert_email:    $( '#ag-alert-email' ).val(),
			retention_days: $( '#ag-retention-days' ).val(),
		}, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Save Settings' );
			showStatus( $( '.js-general-save-status' ), data.message );
		}, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Save Settings' );
			showStatus( $( '.js-general-save-status' ), data.message, true );
		} );
	} );

	// ── Bot Control — radio pills ─────────────────────────────────────────────
	$( document ).on( 'change', '.js-category-rule', function () {
		var $radio   = $( this );
		var catId    = $radio.data( 'category' );
		var rule     = $radio.val(); // 'allow' or 'block'
		var $card    = $radio.closest( '.agentgarrison-bot-category' );

		// Update pill active states.
		$card.find( '.agentgarrison-radio-pill' ).removeClass( 'is-active' );
		$radio.closest( '.agentgarrison-radio-pill' ).addClass( 'is-active' );

		// Update all "inherit" rows status pills to reflect new category rule.
		$card.find( '.js-bot-override' ).each( function () {
			if ( 'inherit' === $( this ).val() ) {
				var $pill = $( this ).closest( 'tr' ).find( '.js-status-pill' );
				$pill
					.text( 'allow' === rule ? 'Allowed' : 'Blocked' )
					.toggleClass( 'is-allowed', 'allow' === rule )
					.toggleClass( 'is-blocked', 'block' === rule );
			}
		} );
	} );

	// Update status pill when override changes.
	$( document ).on( 'change', '.js-bot-override', function () {
		var $select  = $( this );
		var val      = $select.val();
		var $card    = $select.closest( '.agentgarrison-bot-category' );
		var catRule  = $card.find( '.js-category-rule:checked' ).val() || 'allow';
		var $pill    = $select.closest( 'tr' ).find( '.js-status-pill' );
		var effective = ( 'inherit' === val ) ? catRule : val;

		$pill
			.text( 'allow' === effective ? 'Allowed' : 'Blocked' )
			.toggleClass( 'is-allowed', 'allow' === effective )
			.toggleClass( 'is-blocked', 'block' === effective );
	} );

	$( document ).on( 'click', '.js-save-bot-settings', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );

		var categories = {};
		$( '[name^="categories["]' ).each( function () {
			var key = $( this ).attr( 'name' ).replace( 'categories[', '' ).replace( ']', '' );
			categories[ key ] = $( this ).is( ':checked' ) ? 'allow' : 'block';
		} );

		var bots = {};
		$( '[name^="bots["]' ).each( function () {
			var key = $( this ).attr( 'name' ).replace( 'bots[', '' ).replace( ']', '' );
			bots[ key ] = $( this ).val();
		} );

		bwPost( 'rayetun_ag_save_bot_settings', {
			categories:  categories,
			bots:        bots,
			x_robots_tag: $( '[name="x_robots_tag"]' ).is( ':checked' ) ? 1 : 0,
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-save-status' ), data.message );
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-save-status' ), data.message, true );
		} );
	} );

	$( document ).on( 'click', '.js-import-robots', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Importing...' );
		bwPost( 'rayetun_ag_import_robots_txt', {}, function ( data ) {
			$btn.closest( '.agentgarrison-callout' ).html(
				'<span style="color:#2E7D32">✅ ' + data.message + '</span>'
			);
		}, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Import Now' );
			alert( data.message );
		} );
	} );

	// ── Honeypot ──────────────────────────────────────────────────────────────
	function bwSaveHoneypot( clearBlocked, $btn ) {
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_honeypot_settings', {
			auto_block:    $( '#ag-hp-autoblock' ).is( ':checked' ) ? 1 : 0,
			clear_blocked: clearBlocked ? 1 : 0,
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			$( '.js-hp-blocked-count' ).text( data.blocked );
			showStatus( $( '.js-honeypot-status' ), data.message );
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-honeypot-status' ), data.message, true );
		} );
	}

	$( document ).on( 'click', '.js-save-honeypot-settings', function () {
		bwSaveHoneypot( false, $( this ) );
	} );

	$( document ).on( 'click', '.js-clear-honeypot-blocked', function () {
		if ( ! window.confirm( bw.strings.confirm ) ) { return; }
		bwSaveHoneypot( true, $( this ) );
	} );

	// ── llms.txt ──────────────────────────────────────────────────────────────
	// Manual-override toggle reveals/hides the full-file textarea.
	$( document ).on( 'change', '#ag-manual-mode', function () {
		$( '.js-manual-content' ).toggle( $( this ).is( ':checked' ) );
	} );

	$( document ).on( 'click', '.js-save-llms-settings', function () {
		var $btn    = $( this );
		var types   = [];
		$( '[name="post_types[]"]:checked' ).each( function () { types.push( $( this ).val() ); } );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_llms_settings', {
			site_context:     $( '[name="site_context"]' ).val(),
			post_types:       types,
			custom_additions: $( '[name="custom_additions"]' ).val(),
			manual_mode:      $( '#ag-manual-mode' ).is( ':checked' ) ? 1 : 0,
			manual_content:   $( '[name="manual_content"]' ).val(),
			static_file:      $( '#ag-static-file' ).is( ':checked' ) ? 1 : 0,
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-save-status' ), data.message );
		}, function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	// Holds the most recently fetched llms.txt content for copy/download.
	var bwLlmsContent = '';

	$( document ).on( 'click', '.js-regenerate-llms', function () {
		var $btn     = $( this );
		var $preview = $( '.js-llms-preview' );
		$btn.prop( 'disabled', true ).text( bw.strings.regenerating );
		bwPost( 'rayetun_ag_regenerate_llms', {}, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Regenerate Now' );
			$preview.text( data.preview );
			bwLlmsContent = data.preview;
		}, function () {
			$btn.prop( 'disabled', false ).text( 'Regenerate Now' );
		} );
	} );

	$( document ).on( 'click', '.js-preview-llms', function () {
		var $btn     = $( this );
		var $preview = $( '.js-llms-preview' );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_preview_llms', {}, function ( data ) {
			$btn.prop( 'disabled', false );
			$preview.text( data.preview );
			bwLlmsContent = data.preview;
		}, function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	// Copy llms.txt to clipboard.
	$( document ).on( 'click', '.js-copy-llms', function () {
		var $btn = $( this );
		function doCopy( text ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( function () {
					$btn.text( 'Copied!' );
					setTimeout( function () { $btn.text( 'Copy' ); }, 1500 );
				} );
			} else {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				document.body.appendChild( ta );
				ta.select();
				try { document.execCommand( 'copy' ); } catch ( e ) {}
				document.body.removeChild( ta );
				$btn.text( 'Copied!' );
				setTimeout( function () { $btn.text( 'Copy' ); }, 1500 );
			}
		}
		if ( bwLlmsContent ) {
			doCopy( bwLlmsContent );
		} else {
			bwPost( 'rayetun_ag_preview_llms', {}, function ( data ) {
				bwLlmsContent = data.preview;
				$( '.js-llms-preview' ).text( data.preview );
				doCopy( data.preview );
			} );
		}
	} );

	// Download llms.txt as a file.
	$( document ).on( 'click', '.js-download-llms', function () {
		function doDownload( text ) {
			var blob = new Blob( [ text ], { type: 'text/plain' } );
			var url  = URL.createObjectURL( blob );
			var a    = document.createElement( 'a' );
			a.href = url;
			a.download = 'llms.txt';
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			URL.revokeObjectURL( url );
		}
		if ( bwLlmsContent ) {
			doDownload( bwLlmsContent );
		} else {
			bwPost( 'rayetun_ag_preview_llms', {}, function ( data ) {
				bwLlmsContent = data.preview;
				$( '.js-llms-preview' ).text( data.preview );
				doDownload( data.preview );
			} );
		}
	} );

	// Validator.
	$( document ).on( 'click', '.js-validate-llms', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Validating...' );
		bwPost( 'rayetun_ag_validate_llms', {}, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Run Validator' );
			$( '.js-validator-body' ).show();

			// Stats.
			$( '.js-vstat-size' ).text( data.stats.size );
			$( '.js-vstat-tokens' ).text( Number( data.stats.tokens ).toLocaleString() );
			$( '.js-vstat-included' ).text( data.stats.included );
			$( '.js-vstat-excluded' ).text( data.stats.excluded );
			$( '.js-vstat-sections' ).text( data.stats.sections );

			// Checks.
			var $checks = $( '.js-validator-checks' ).empty();
			$.each( data.checks, function ( i, c ) {
				$checks.append(
					'<div class="agentgarrison-vcheck ' + ( c.pass ? 'is-pass' : 'is-fail' ) + '">' +
						'<span class="agentgarrison-vcheck__icon">' + ( c.pass ? '✓' : '✕' ) + '</span>' +
						'<div class="agentgarrison-vcheck__text"><strong>' + bwEsc( c.label ) + '</strong>' +
						'<span>' + bwEsc( c.detail ) + '</span></div>' +
					'</div>'
				);
			} );

			// Warnings.
			var $warn = $( '.js-validator-warnings' ).empty();
			if ( data.warnings && data.warnings.length ) {
				$.each( data.warnings, function ( i, w ) {
					$warn.append( '<div class="agentgarrison-vwarning">⚠ ' + bwEsc( w ) + '</div>' );
				} );
			}

			// Keep content in sync for copy/download.
			if ( data.content ) {
				bwLlmsContent = data.content;
				$( '.js-llms-preview' ).text( data.content );
			}
		}, function () {
			$btn.prop( 'disabled', false ).text( 'Run Validator' );
		} );
	} );

	// ── Analytics ─────────────────────────────────────────────────────────────
	var timelineChart = null;
	var botChart      = null;

	window.agentgarrisonAnalytics = {
		init: function ( days ) {
			var $active = $( '.agentgarrison-range-btn.is-active' );
			if ( $active.length ) {
				days = parseInt( $active.data( 'days' ), 10 ) || days;
			}
			this.load( days );
		},
		load: function ( days ) {
			bwPost( 'rayetun_ag_get_analytics', { days: days }, function ( data ) {
				agentgarrisonAnalytics.render( data );
			} );
		},
		render: function ( data ) {
			$( '.js-total-visits' ).text( data.total.toLocaleString() );
			$( '.js-unique-bots' ).text( data.by_bot ? data.by_bot.length : 0 );

			// Timeline chart.
			var labels = data.by_day.map( function ( r ) { return r.day; } );
			var values = data.by_day.map( function ( r ) { return parseInt( r.visits, 10 ); } );

			if ( timelineChart ) { timelineChart.destroy(); }
			var ctx1 = document.getElementById( 'ag-chart-timeline' );
			if ( ctx1 ) {
				timelineChart = new Chart( ctx1.getContext( '2d' ), {
					type: 'line',
					data: {
						labels: labels,
						datasets: [ {
							label: 'Bot Visits',
							data: values,
							fill: true,
							backgroundColor: 'rgba(15,92,107,0.08)',
							borderColor: '#0F5C6B',
							borderWidth: 2,
							tension: 0.4,
							pointRadius: 2,
							pointHoverRadius: 5,
						} ],
					},
					options: {
						maintainAspectRatio: false,
						plugins: { legend: { display: false } },
						scales: {
							y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
							x: { grid: { display: false } },
						},
					},
				} );
			}

			// By-bot donut chart — compact.
			var botLabels = data.by_bot.map( function ( r ) { return r.bot_name; } );
			var botVals   = data.by_bot.map( function ( r ) { return parseInt( r.visits, 10 ); } );
			var colors    = [ '#0F5C6B','#F0A500','#2E7D32','#C62828','#5C7880','#094550','#7AB8C4','#1A2B2E','#a0c4cc','#d4a500' ];

			if ( botChart ) { botChart.destroy(); }
			var ctx2 = document.getElementById( 'ag-chart-by-bot' );
			if ( ctx2 ) {
				botChart = new Chart( ctx2.getContext( '2d' ), {
					type: 'doughnut',
					data: {
						labels: botLabels,
						datasets: [ {
							data: botVals,
							backgroundColor: colors,
							borderWidth: 2,
							borderColor: '#fff',
						} ],
					},
					options: {
						maintainAspectRatio: false,
						cutout: '68%',
						plugins: {
							legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 } } },
						},
					},
				} );
			}

			// Top pages table.
			var $tbody = $( '.js-top-pages-table tbody' );
			$tbody.empty();
			if ( data.top_pages.length ) {
				$.each( data.top_pages, function ( i, row ) {
					$tbody.append(
						'<tr><td class="agentgarrison-truncate">' + $( '<span>' ).text( row.page_url ).html() +
						'</td><td class="agentgarrison-col-num">' + parseInt( row.visits, 10 ).toLocaleString() + '</td></tr>'
					);
				} );
			} else {
				$tbody.append( '<tr><td colspan="2" class="agentgarrison-loading">No data yet.</td></tr>' );
			}
		},
	};

	$( document ).on( 'click', '.agentgarrison-range-btn[data-days]', function () {
		$( this ).siblings( '[data-days]' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		agentgarrisonAnalytics.load( parseInt( $( this ).data( 'days' ), 10 ) );
	} );

	// ── Referrals ─────────────────────────────────────────────────────────────
	var referralChart = null;

	window.agentgarrisonReferrals = {
		init: function ( days ) { this.load( days ); },
		load: function ( days ) {
			bwPost( 'rayetun_ag_get_referrals', { days: days }, function ( data ) {
				agentgarrisonReferrals.render( data );
			} );
		},
		render: function ( data ) {
			$( '.js-ref-total' ).text( data.total.toLocaleString() );
			$( '.js-ref-platforms' ).text( data.by_platform ? data.by_platform.length : 0 );

			var labels = data.by_platform.map( function ( r ) { return r.label; } );
			var values = data.by_platform.map( function ( r ) { return parseInt( r.cnt, 10 ); } );
			var colors = [ '#0F5C6B','#F0A500','#2E7D32','#C62828','#5C7880' ];

			if ( referralChart ) { referralChart.destroy(); }
			var ctx = document.getElementById( 'ag-chart-referrals' );
			if ( ctx ) {
				referralChart = new Chart( ctx.getContext( '2d' ), {
					type: 'bar',
					data: { labels: labels, datasets: [ { label: 'Referrals', data: values, backgroundColor: colors, borderRadius: 6 } ] },
					options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
				} );
			}

			var $tbody = $( '.js-ref-pages-table tbody' );
			$tbody.empty();
			if ( data.top_pages.length ) {
				$.each( data.top_pages, function ( i, row ) {
					$tbody.append(
						'<tr><td class="agentgarrison-truncate">' + $( '<span>' ).text( row.title ).html() +
						'</td><td><span class="agentgarrison-badge">' + parseInt( row.cnt, 10 ) + '</span></td></tr>'
					);
				} );
			} else {
				$tbody.append( '<tr><td colspan="2" class="agentgarrison-loading">No referrals logged yet.</td></tr>' );
			}
		},
	};

	$( document ).on( 'click', '.agentgarrison-range-btn[data-ref-days]', function () {
		$( this ).siblings( '[data-ref-days]' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		agentgarrisonReferrals.load( parseInt( $( this ).data( 'ref-days' ), 10 ) );
	} );

	// ── Content Scorer ─────────────────────────────────────────────────────────
	window.agentgarrisonScorer = {
		filter: 'all',
		init: function () { this.load( 1 ); },
		load: function ( page ) {
			var self = this;
			bwPost( 'rayetun_ag_get_content_scores', { paged: page, filter: this.filter }, function ( data ) {
				self.render( data );
			} );
		},
		render: function ( data ) {
			var $tbody = $( '.js-scores-table tbody' );
			$tbody.empty();
			if ( ! data.items.length ) {
				$tbody.append( '<tr><td colspan="4" class="agentgarrison-loading">No scored posts in this range. Save a post to score it.</td></tr>' );
				$( '.js-scores-pagination' ).empty();
				return;
			}
			$.each( data.items, function ( i, item ) {
				var cls = item.score >= 70 ? 'is-good' : ( item.score >= 50 ? 'is-mid' : 'is-low' );
				$tbody.append(
					'<tr>' +
					'<td>' + $( '<a>' ).attr( 'href', item.edit_link ).text( item.title ).prop( 'outerHTML' ) + '</td>' +
					'<td><span class="agentgarrison-badge">' + item.type + '</span></td>' +
					'<td><span class="agentgarrison-score-pill ' + cls + '">' + item.score + '</span></td>' +
					'<td><button class="agentgarrison-btn agentgarrison-btn--sm js-rescore" data-id="' + item.id + '">Re-score</button></td>' +
					'</tr>'
				);
			} );
			this.renderPagination( data.paged, data.pages );
		},
		renderPagination: function ( current, total ) {
			var $p = $( '.js-scores-pagination' );
			if ( total <= 1 ) { $p.empty(); return; }
			var html = '';
			if ( current > 1 ) { html += '<button class="agentgarrison-btn agentgarrison-btn--sm js-scores-page" data-page="' + ( current - 1 ) + '">← Prev</button>'; }
			html += '<span class="agentgarrison-page-info">Page ' + current + ' of ' + total + '</span>';
			if ( current < total ) { html += '<button class="agentgarrison-btn agentgarrison-btn--sm js-scores-page" data-page="' + ( current + 1 ) + '">Next →</button>'; }
			$p.html( html );
		},
	};

	$( document ).on( 'click', '.agentgarrison-range-btn[data-filter]', function () {
		$( this ).siblings( '[data-filter]' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		agentgarrisonScorer.filter = $( this ).data( 'filter' );
		agentgarrisonScorer.load( 1 );
	} );

	$( document ).on( 'click', '.js-scores-page', function () {
		agentgarrisonScorer.load( parseInt( $( this ).data( 'page' ), 10 ) );
	} );

	$( document ).on( 'click', '.js-rescore', function () {
		var $btn = $( this );
		var id   = $btn.data( 'id' );
		$btn.prop( 'disabled', true ).text( '...' );
		bwPost( 'rayetun_ag_rescore_post', { post_id: id }, function ( data ) {
			var cls = data.score >= 70 ? 'is-good' : ( data.score >= 50 ? 'is-mid' : 'is-low' );
			$btn.closest( 'tr' ).find( '.agentgarrison-score-pill' )
				.attr( 'class', 'agentgarrison-score-pill ' + cls ).text( data.score );
			$btn.prop( 'disabled', false ).text( 'Re-score' );
		}, function () {
			$btn.prop( 'disabled', false ).text( 'Re-score' );
		} );
	} );

	// ── Schema — Organization preview (built client-side from form values) ────
	$( document ).on( 'click', '.js-preview-org-schema', function () {
		var name   = $( '#ag-org-name' ).val() || document.title;
		var desc   = $( '#ag-org-description' ).val();
		var logo   = $( '#ag-org-logo' ).val();

		var schema = {
			'@context': 'https://schema.org',
			'@graph': [ {
				'@type': 'Organization',
				'@id': window.location.origin + '/#organization',
				'name': name,
				'url': window.location.origin + '/',
			} ],
		};
		var org = schema['@graph'][0];
		if ( desc ) { org['description'] = desc; }
		if ( logo ) { org['logo'] = { '@type': 'ImageObject', 'url': logo }; }

		var pretty = JSON.stringify( schema, null, 2 );
		$( '.js-schema-preview-title' ).text( 'Organization Schema' );
		$( '.js-schema-preview' ).text( pretty );
		$( '.js-schema-preview-meta' ).show();
	} );

	// ── Schema — full @graph preview (server-side, latest post) ───────────────
	$( document ).on( 'click', '.js-preview-full-schema', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_preview_post_schema', { post_id: 0 }, function ( data ) {
			$btn.prop( 'disabled', false );
			$( '.js-schema-preview-title' ).text( 'Full Page Schema — ' + ( data.title || 'latest post' ) );
			$( '.js-schema-preview' ).text( data.json );
			$( '.js-schema-preview-meta' ).show();
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			$( '.js-schema-preview' ).text( ( data && data.message ) || 'Could not build preview.' );
		} );
	} );

	// ── Schema — validator ────────────────────────────────────────────────────
	$( document ).on( 'click', '.js-validate-schema', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Validating...' );
		bwPost( 'rayetun_ag_validate_schema', { post_id: 0 }, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Validate Schema' );
			$( '.js-schema-validator' ).show();
			$( '.js-validator-post-title' ).text( data.title || 'latest post' );
			$( '.js-validator-summary' ).text(
				data.node_count + ' schema node' + ( 1 === data.node_count ? '' : 's' ) +
				' across ' + data.types.length + ' type' + ( 1 === data.types.length ? '' : 's' ) + '.'
			);
			$( '.js-rich-results-link' ).attr( 'href', data.rich_results_url );

			// Type chips.
			var $types = $( '.js-validator-types' ).empty();
			$.each( data.types, function ( i, t ) {
				$types.append( '<span class="agentgarrison-type-chip">' + bwEsc( t ) + '</span>' );
			} );

			// Recommendations.
			var $recs = $( '.js-validator-recommendations' ).empty();
			if ( data.recommendations && data.recommendations.length ) {
				$( '.js-validator-recommendations-wrap' ).show();
				$.each( data.recommendations, function ( i, r ) {
					$recs.append(
						'<div class="agentgarrison-vcheck is-fail">' +
							'<span class="agentgarrison-vcheck__icon">!</span>' +
							'<div class="agentgarrison-vcheck__text"><strong>' + bwEsc( r.type ) + '</strong>' +
							'<span>Missing recommended: ' + bwEsc( r.missing.join( ', ' ) ) + '</span></div>' +
						'</div>'
					);
				} );
			} else {
				$( '.js-validator-recommendations-wrap' ).show();
				$recs.html( '<div class="agentgarrison-vcheck is-pass"><span class="agentgarrison-vcheck__icon">✓</span><div class="agentgarrison-vcheck__text"><strong>All recommended properties present</strong><span>Every emitted type has its key properties.</span></div></div>' );
			}

			// Conflict report.
			if ( data.conflict && data.conflict.active ) {
				$( '.js-validator-conflict-wrap' ).show();
				$( '.js-validator-conflict' ).text(
					'⚠ ' + data.conflict.plugin + ' is active. AgentGarrison defers these types to avoid duplicate markup: ' +
					data.conflict.deferred.join( ', ' ) + '. FAQ, HowTo, Organization, WebSite, Breadcrumb, and Product still apply.'
				);
			} else {
				$( '.js-validator-conflict-wrap' ).hide();
			}
		}, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Validate Schema' );
			alert( ( data && data.message ) || 'Validation failed.' );
		} );
	} );

	// ── Schema — per-post metabox preview (post editor) ───────────────────────
	$( document ).on( 'click', '.js-preview-post-schema', function () {
		var $btn  = $( this );
		var $pane = $( '.js-post-schema-preview' );
		var pid   = $( '#post_ID' ).val() || 0;
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_preview_post_schema', { post_id: pid }, function ( data ) {
			$btn.prop( 'disabled', false );
			$pane.text( data.json ).show();
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			$pane.text( ( data && data.message ) || 'Could not build preview.' ).show();
		} );
	} );

	// ── Schema settings ────────────────────────────────────────────────────────
	$( document ).on( 'click', '.js-save-schema-settings', function () {
		var $btn = $( this );
		var data = {
			organization:    $( '[name="organization"]' ).val(),
			org_description: $( '[name="org_description"]' ).val(),
			org_logo:        $( '[name="org_logo"]' ).val(),
			publishing_principles: $( '[name="publishing_principles"]' ).val(),
			ethics_policy:         $( '[name="ethics_policy"]' ).val(),
			corrections_policy:    $( '[name="corrections_policy"]' ).val(),
			ownership_funding:     $( '[name="ownership_funding"]' ).val(),
			founding_date:         $( '[name="founding_date"]' ).val(),
			same_as:               $( '[name="same_as"]' ).val(),
			default_reviewer:      $( '[name="default_reviewer"]' ).val(),
			local_business:        $( '#ag-lb-enable' ).is( ':checked' ) ? 1 : 0,
			lb_type:    $( '[name="lb_type"]' ).val(),
			lb_phone:   $( '[name="lb_phone"]' ).val(),
			lb_price:   $( '[name="lb_price"]' ).val(),
			lb_street:  $( '[name="lb_street"]' ).val(),
			lb_city:    $( '[name="lb_city"]' ).val(),
			lb_region:  $( '[name="lb_region"]' ).val(),
			lb_postal:  $( '[name="lb_postal"]' ).val(),
			lb_country: $( '[name="lb_country"]' ).val(),
			lb_lat:     $( '[name="lb_lat"]' ).val(),
			lb_lng:     $( '[name="lb_lng"]' ).val(),
			lb_hours:   $( '[name="lb_hours"]' ).val(),
		};
		$( '.agentgarrison-schema-type' ).each( function () {
			data[ $( this ).attr( 'name' ) ] = $( this ).is( ':checked' ) ? 1 : 0;
		} );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_schema_settings', data, function ( res ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-schema-save-status' ), res.message );
		}, function ( res ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-schema-save-status' ), res.message, true );
		} );
	} );

	// ── Dark mode toggle ──────────────────────────────────────────────────────
	$( document ).on( 'click', '.js-dark-toggle', function () {
		var $wrap  = $( '.agentgarrison-wrap' );
		var isDark = ! $wrap.hasClass( 'agentgarrison-dark' );
		$wrap.toggleClass( 'agentgarrison-dark', isDark );
		$( '.agentgarrison-dark-toggle__icon' ).text( isDark ? '☀️' : '🌙' );
		bwPost( 'rayetun_ag_toggle_dark_mode', { enabled: isDark ? 1 : 0 }, function () {}, function () {} );
	} );

	// ── Email Digest settings ─────────────────────────────────────────────────
	$( document ).on( 'change', '.js-digest-freq', function () {
		var $pills = $( this ).closest( '.agentgarrison-radio-pills' ).find( '.agentgarrison-radio-pill' );
		$pills.removeClass( 'is-active' );
		$( this ).closest( '.agentgarrison-radio-pill' ).addClass( 'is-active' );
	} );

	$( document ).on( 'click', '.js-save-digest-settings', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_digest_settings', {
			email:       $( '[name="digest_email"]' ).val(),
			frequency:   $( '[name="digest_frequency"]:checked' ).val() || 'weekly',
			agency_name: $( '[name="agency_name"]' ).val(),
			enabled:     1,
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-digest-save-status' ), data.message );
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-digest-save-status' ), data.message, true );
		} );
	} );

	$( document ).on( 'click', '.js-send-test-digest', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Sending...' );
		bwPost( 'rayetun_ag_send_test_digest', {}, function ( data ) {
			$btn.prop( 'disabled', false ).text( 'Send Test Digest' );
			showStatus( $( '.js-digest-save-status' ), data.message );
		}, function () {
			$btn.prop( 'disabled', false ).text( 'Send Test Digest' );
		} );
	} );

	// ── Citation Monitor ──────────────────────────────────────────────────────
	function bwEsc( str ) { return $( '<span>' ).text( str == null ? '' : str ).html(); }
	// Only allow http(s) URLs in hrefs. cited_url originates from external AI APIs,
	// so a javascript:/data: scheme must never survive into an href attribute.
	function bwSafeUrl( url ) {
		url = ( url == null ? '' : String( url ) ).trim();
		return /^https?:\/\//i.test( url ) ? bwEsc( url ) : '#';
	}

	window.agentgarrisonCitations = {
		platforms: {},
		init: function () { this.load(); },
		load: function () {
			var self = this;
			bwPost( 'rayetun_ag_get_citations', {}, function ( data ) {
				self.platforms = data.platforms || {};
				self.renderKeywords( data.keywords );
				self.renderResults( data.results );
				self.renderScorecard( data.scorecard );
			} );
		},
		renderScorecard: function ( sc ) {
			var $card = $( '.js-citation-scorecard' );
			if ( ! sc || ! sc.has_data ) { $card.hide(); return; }
			$card.show();
			$( '.js-scorecard-demo' ).toggle( !! sc.is_demo );
			$( '.js-scorecard-rate' ).text( sc.cited_rate + '%' );
			$( '.js-scorecard-summary' ).text( 'cited for ' + sc.cited_count + ' of ' + sc.total_count + ' questions' );

			var $q = $( '.js-scorecard-questions' ).empty();
			$.each( sc.questions || [], function ( i, item ) {
				$q.append(
					'<li class="agentgarrison-scorecard__q ' + ( item.cited ? 'is-cited' : 'is-missed' ) + '">' +
						'<span class="agentgarrison-scorecard__mark">' + ( item.cited ? '✓' : '✕' ) + '</span>' +
						bwEsc( item.keyword ) +
					'</li>'
				);
			} );

			var $c = $( '.js-scorecard-competitors' ).empty();
			if ( ! ( sc.competitors && sc.competitors.length ) ) {
				$c.append( '<li class="agentgarrison-scorecard__empty">No competing domains cited yet.</li>' );
			} else {
				$.each( sc.competitors, function ( i, c ) {
					$c.append(
						'<li class="agentgarrison-scorecard__comp">' +
							'<span class="agentgarrison-scorecard__domain">' + bwEsc( c.domain ) + '</span>' +
							'<span class="agentgarrison-scorecard__count">' + parseInt( c.count, 10 ) + '</span>' +
						'</li>'
					);
				} );
			}
		},
		renderKeywords: function ( keywords ) {
			var $list = $( '.js-keyword-list' );
			$list.empty();
			if ( ! keywords || ! keywords.length ) {
				$list.html( '<p class="agentgarrison-empty-state">No keywords yet. Add one above to start tracking.</p>' );
				return;
			}
			$.each( keywords, function ( i, kw ) {
				$list.append(
					'<span class="agentgarrison-keyword-chip">' + bwEsc( kw.keyword ) +
					'<button class="agentgarrison-keyword-chip__remove js-delete-keyword" data-id="' + parseInt( kw.id, 10 ) + '">×</button></span>'
				);
			} );
		},
		renderResults: function ( results ) {
			var $wrap = $( '.js-citation-results' );
			$wrap.empty();
			if ( ! results || ! results.length ) {
				$wrap.html( '<p class="agentgarrison-empty-state">No citations recorded yet. Add keywords and run a demo scan, or configure API keys for live data.</p>' );
				return;
			}
			var self = this;
			$.each( results, function ( i, r ) {
				var pf    = self.platforms[ r.platform ] || { label: r.platform, color: '#5C7880' };
				var demo  = parseInt( r.is_demo, 10 ) ? '<span class="agentgarrison-demo-tag">DEMO</span>' : '';
				var conf  = parseInt( r.confidence_score, 10 );
				var confC = conf >= 85 ? '#2E7D32' : ( conf >= 70 ? '#F0A500' : '#C62828' );
				var title = r.post_title || r.cited_url;
				$wrap.append(
					'<div class="agentgarrison-citation-item">' +
						'<div class="agentgarrison-citation-item__head">' +
							'<span class="agentgarrison-citation-platform" style="background:' + pf.color + '">' + bwEsc( pf.label ) + '</span>' +
							demo +
							'<span class="agentgarrison-citation-conf" style="color:' + confC + '">' + conf + '% match</span>' +
						'</div>' +
						'<div class="agentgarrison-citation-item__kw">🔎 ' + bwEsc( r.keyword ) + '</div>' +
						'<a href="' + bwSafeUrl( r.cited_url ) + '" target="_blank" rel="noopener" class="agentgarrison-citation-item__url">' + bwEsc( title ) + '</a>' +
						( r.context_snippet ? '<p class="agentgarrison-citation-item__snippet">' + bwEsc( r.context_snippet ) + '</p>' : '' ) +
					'</div>'
				);
			} );
		},
	};

	$( document ).on( 'click', '.js-add-keyword', function () {
		var $input = $( '.js-keyword-input' );
		var kw     = $input.val().trim();
		if ( ! kw ) { return; }
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_add_keyword', { keyword: kw }, function () {
			$input.val( '' );
			$btn.prop( 'disabled', false );
			agentgarrisonCitations.load();
		}, function ( d ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-citation-status' ), d.message, true );
		} );
	} );

	$( document ).on( 'keypress', '.js-keyword-input', function ( e ) {
		if ( 13 === e.which ) { e.preventDefault(); $( '.js-add-keyword' ).trigger( 'click' ); }
	} );

	$( document ).on( 'click', '.js-delete-keyword', function () {
		var id = $( this ).data( 'id' );
		bwPost( 'rayetun_ag_delete_keyword', { id: id }, function () {
			agentgarrisonCitations.load();
		} );
	} );

	$( document ).on( 'click', '.js-run-live-scan', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Scanning live…' );
		showStatus( $( '.js-citation-status' ), 'Querying your AI provider — this can take a moment.' );
		bwPost( 'rayetun_ag_run_live_scan', {}, function ( data ) {
			$btn.prop( 'disabled', false ).text( '🔎 Run Live Scan' );
			showStatus( $( '.js-citation-status' ), data.message );
			agentgarrisonCitations.load();
		}, function ( data ) {
			$btn.prop( 'disabled', false ).text( '🔎 Run Live Scan' );
			showStatus( $( '.js-citation-status' ), data.message, true );
		} );
	} );

	$( document ).on( 'click', '.js-run-demo-scan', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Scanning...' );
		bwPost( 'rayetun_ag_run_demo_scan', {}, function ( data ) {
			$btn.prop( 'disabled', false ).text( '⚡ Run Demo Scan' );
			showStatus( $( '.js-citation-status' ), data.message );
			agentgarrisonCitations.load();
		}, function ( data ) {
			$btn.prop( 'disabled', false ).text( '⚡ Run Demo Scan' );
			showStatus( $( '.js-citation-status' ), data.message, true );
		} );
	} );

	$( document ).on( 'click', '.js-clear-citations', function () {
		if ( ! window.confirm( bw.strings.confirm ) ) { return; }
		bwPost( 'rayetun_ag_clear_citations', { demo_only: 1 }, function () {
			agentgarrisonCitations.load();
		} );
	} );

	$( document ).on( 'click', '.js-save-api-keys', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_api_keys', {
			anthropic:  $( '#ag-cm-anthropic' ).val(),
			openai:     $( '#ag-cm-openai' ).val(),
			perplexity: $( '#ag-cm-perplexity' ).val(),
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-api-status' ), data.message );
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-api-status' ), data.message, true );
		} );
	} );

	$( document ).on( 'click', '.js-save-citation-settings', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_citation_settings', {
			scan_frequency: $( '#ag-cm-frequency' ).val(),
			notify_enabled: $( '#ag-cm-notify' ).is( ':checked' ) ? 1 : 0,
			notify_email:   $( '#ag-cm-notify-email' ).val(),
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-citation-settings-status' ), data.message );
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-citation-settings-status' ), data.message, true );
		} );
	} );

	// ── White-label Reports ───────────────────────────────────────────────────
	var bwReportHtml = '';

	$( document ).on( 'click', '.js-save-report-settings', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_report_settings', {
			company_name: $( '#ag-rep-company' ).val(),
			logo_url:     $( '#ag-rep-logo' ).val(),
			accent_color: $( '#ag-rep-accent' ).val(),
			footer_text:  $( '#ag-rep-footer' ).val(),
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-report-status' ), data.message );
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-report-status' ), data.message, true );
		} );
	} );

	$( document ).on( 'click', '.js-generate-report', function () {
		var $btn = $( this );
		var days = $( '.js-report-period' ).val();
		$btn.prop( 'disabled', true ).text( 'Generating...' );
		bwPost( 'rayetun_ag_generate_report', { days: days }, function ( data ) {
			bwReportHtml = data.html;
			$btn.prop( 'disabled', false ).text( 'Generate Report' );
			$( '.js-download-report, .js-print-report' ).show();
			// Open report in new tab.
			var w = window.open( '', '_blank' );
			if ( w ) { w.document.write( bwReportHtml ); w.document.close(); }
		}, function () {
			$btn.prop( 'disabled', false ).text( 'Generate Report' );
		} );
	} );

	$( document ).on( 'click', '.js-download-report', function () {
		if ( ! bwReportHtml ) { return; }
		var blob = new Blob( [ bwReportHtml ], { type: 'text/html' } );
		var url  = URL.createObjectURL( blob );
		var a    = document.createElement( 'a' );
		a.href = url;
		a.download = 'agentgarrison-ai-report.html';
		document.body.appendChild( a );
		a.click();
		document.body.removeChild( a );
		URL.revokeObjectURL( url );
	} );

	$( document ).on( 'click', '.js-print-report', function () {
		if ( ! bwReportHtml ) { return; }
		var w = window.open( '', '_blank' );
		if ( w ) {
			w.document.write( bwReportHtml );
			w.document.close();
			w.focus();
			setTimeout( function () { w.print(); }, 400 );
		}
	} );

	// ── Onboarding Wizard ─────────────────────────────────────────────────────
	var bwWizardStep = 1;

	function bwWizardGoTo( step ) {
		bwWizardStep = step;
		$( '.ag-wizard-panel' ).removeClass( 'is-active' );
		$( '.ag-wizard-panel[data-panel="' + step + '"]' ).addClass( 'is-active' );
		$( '.ag-wizard-step' ).each( function () {
			var s = parseInt( $( this ).data( 'step' ), 10 );
			$( this ).toggleClass( 'is-active', s === step ).toggleClass( 'is-done', s < step );
		} );
		$( '.js-wizard-back' ).toggle( step > 1 && step < 4 );
		$( '.js-wizard-next' ).toggle( step < 4 );
		$( '.js-wizard-finish' ).toggle( step === 4 );
		$( '.js-wizard-skip' ).toggle( step < 4 );
		$( '.ag-wizard-footer' ).toggleClass( 'is-final', step === 4 );
	}

	$( document ).on( 'click', '.ag-wizard-choice', function () {
		$( '.ag-wizard-choice' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		$( this ).find( 'input[type=radio]' ).prop( 'checked', true );
	} );

	$( document ).on( 'click', '.js-wizard-next', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		var payload = { step: bwWizardStep };
		if ( 1 === bwWizardStep ) {
			payload.policy = $( 'input[name="bw_bot_policy"]:checked' ).val();
		} else if ( 2 === bwWizardStep ) {
			payload.context = $( '#ag-wizard-context' ).val();
		}
		bwPost( 'rayetun_ag_onboarding_step', payload, function () {
			$btn.prop( 'disabled', false );
			bwWizardGoTo( bwWizardStep + 1 );
		}, function () {
			$btn.prop( 'disabled', false );
			bwWizardGoTo( bwWizardStep + 1 );
		} );
	} );

	$( document ).on( 'click', '.js-wizard-back', function () {
		if ( bwWizardStep > 1 ) { bwWizardGoTo( bwWizardStep - 1 ); }
	} );

	$( document ).on( 'click', '.js-wizard-generate', function () {
		var $btn    = $( this );
		var $status = $( '.js-wizard-generate-status' );
		$btn.prop( 'disabled', true ).text( 'Generating...' );
		bwPost( 'rayetun_ag_onboarding_step', { step: 3 }, function () {
			$status.html( '<div class="ag-wizard-generate-icon">✅</div><p>Your llms.txt has been generated successfully!</p>' );
			$btn.hide();
		}, function () {
			$btn.prop( 'disabled', false ).text( '⚡ Generate Now' );
		} );
	} );

	$( document ).on( 'click', '.js-wizard-skip', function () {
		bwPost( 'rayetun_ag_onboarding_skip', {}, function () {
			$( '#ag-wizard' ).fadeOut( 200 );
		} );
	} );

	$( document ).on( 'click', '.js-wizard-finish', function () {
		bwPost( 'rayetun_ag_onboarding_step', { step: 4 }, function () {
			$( '#ag-wizard' ).fadeOut( 200 );
			window.location.href = 'admin.php?page=agentgarrison';
		}, function () {
			$( '#ag-wizard' ).fadeOut( 200 );
		} );
	} );

	// ── Live Bot Activity feed (AJAX polling) ─────────────────────────────────
	var agentgarrisonLiveFeed = {
		POLL_MS: 15000,   // 15s — comfortably within the prompt-cache / resource budget.
		MAX_ROWS: 30,
		lastId: 0,
		timer: null,

		init: function () {
			var $feed = $( '.agentgarrison-livefeed' );
			if ( ! $feed.length || '1' !== String( $feed.data( 'enabled' ) ) ) {
				return;
			}
			this.lastId = parseInt( $feed.data( 'last-id' ), 10 ) || 0;
			// Poll immediately, then on an interval (paused when tab hidden).
			this.schedule();
			document.addEventListener( 'visibilitychange', function () {
				if ( document.hidden ) {
					agentgarrisonLiveFeed.stop();
				} else {
					agentgarrisonLiveFeed.poll();
					agentgarrisonLiveFeed.schedule();
				}
			} );
		},

		schedule: function () {
			this.stop();
			this.timer = setInterval( function () {
				if ( ! document.hidden ) { agentgarrisonLiveFeed.poll(); }
			}, this.POLL_MS );
		},

		stop: function () {
			if ( this.timer ) { clearInterval( this.timer ); this.timer = null; }
		},

		poll: function () {
			var $dot = $( '.js-live-dot' );
			bwPost( 'rayetun_ag_live_feed', { last_id: this.lastId }, function ( data ) {
				$dot.addClass( 'is-pulse' );
				setTimeout( function () { $dot.removeClass( 'is-pulse' ); }, 1200 );
				if ( data.last_id > agentgarrisonLiveFeed.lastId ) {
					agentgarrisonLiveFeed.lastId = data.last_id;
				}
				agentgarrisonLiveFeed.render( data.items );
			} );
		},

		render: function ( items ) {
			if ( ! items || ! items.length ) { return; }
			var $list = $( '.js-livefeed-list' );
			$( '.js-livefeed-empty' ).remove();

			// Server returns newest-first; insert so newest ends up on top.
			items.slice().reverse().forEach( function ( it ) {
				var row = $(
					'<div class="agentgarrison-feed-row is-new" data-id="' + it.id + '">' +
						'<span class="agentgarrison-feed-row__bot agentgarrison-badge">' + bwEsc( it.bot_name ) + '</span>' +
						'<span class="agentgarrison-feed-row__path agentgarrison-truncate">' + bwEsc( it.page_path ) + '</span>' +
						'<span class="agentgarrison-feed-row__time">' + bwEsc( it.ago ) + ' ago</span>' +
					'</div>'
				);
				$list.prepend( row );
				// Trigger the fade-in highlight on the next frame.
				window.requestAnimationFrame( function () { row.removeClass( 'is-new' ); } );
			} );

			// Trim to keep the DOM light.
			$list.find( '.agentgarrison-feed-row' ).slice( agentgarrisonLiveFeed.MAX_ROWS ).remove();
		},
	};

	// Auto-init page controllers based on which tab's markup is present.
	// (Replaces the per-view inline scripts, per WordPress.org enqueue guidelines.)
	$( function () {
		agentgarrisonLiveFeed.init();
		if ( $( '#ag-chart-timeline' ).length && typeof agentgarrisonAnalytics !== 'undefined' ) {
			agentgarrisonAnalytics.init( 7 );
		}
		if ( $( '#ag-chart-referrals' ).length && typeof agentgarrisonReferrals !== 'undefined' ) {
			agentgarrisonReferrals.init( 7 );
		}
		if ( $( '.js-scores-table' ).length && typeof agentgarrisonScorer !== 'undefined' ) {
			agentgarrisonScorer.init();
		}
		if ( $( '.js-keyword-list' ).length && typeof agentgarrisonCitations !== 'undefined' ) {
			agentgarrisonCitations.init();
		}
		if ( $( '.js-markdown-preview' ).length && typeof agentgarrisonMarkdown !== 'undefined' ) {
			agentgarrisonMarkdown.preview( 0 );
		}
	} );

	// ── Markdown for Agents ───────────────────────────────────────────────────
	window.agentgarrisonMarkdown = {
		preview: function ( postId ) {
			var $pane = $( '.js-markdown-preview' );
			bwPost( 'rayetun_ag_preview_markdown', { post_id: postId || 0 }, function ( data ) {
				$( '.js-markdown-preview-title' ).text( 'Preview — ' + ( data.title || 'latest post' ) );
				$( '.js-markdown-meta' ).html(
					'≈ ' + Number( data.tokens ).toLocaleString() + ' tokens · ' +
					'<a href="' + bwEsc( data.url ) + '" target="_blank" rel="noopener">open markdown URL ↗</a>'
				);
				$pane.text( data.preview );
			}, function ( data ) {
				$pane.text( ( data && data.message ) || 'Could not build preview.' );
			} );
		},
	};

	$( document ).on( 'click', '.js-preview-markdown', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		agentgarrisonMarkdown.preview( 0 );
		setTimeout( function () { $btn.prop( 'disabled', false ); }, 600 );
	} );

	$( document ).on( 'click', '.js-save-markdown-settings', function () {
		var $btn  = $( this );
		var types = [];
		$( '[name="md_post_types[]"]:checked' ).each( function () { types.push( $( this ).val() ); } );
		$btn.prop( 'disabled', true );
		bwPost( 'rayetun_ag_save_markdown_settings', {
			post_types:   types,
			frontmatter:  $( '#ag-md-frontmatter' ).is( ':checked' ) ? 1 : 0,
			token_header: $( '#ag-md-token' ).is( ':checked' ) ? 1 : 0,
			discovery:    $( '#ag-md-discovery' ).is( ':checked' ) ? 1 : 0,
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-markdown-status' ), data.message );
			agentgarrisonMarkdown.preview( 0 );
		}, function ( data ) {
			$btn.prop( 'disabled', false );
			showStatus( $( '.js-markdown-status' ), data.message, true );
		} );
	} );

} )( jQuery );
