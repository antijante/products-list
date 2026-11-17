import { describe, expect, it } from 'vitest';
import { blockOutline } from '../../resources/edit/block-outline';

const titles = {
	types: {
		'antijante-woocommerce/woo-product': { title: 'Product', part: false },
		'woocommerce/single-product': { title: 'Product', part: false },
		'woocommerce/product-price': { title: 'Product Price', part: true },
		'gds/gallery-carousel': { title: 'Gallery carousel', part: false },
		'gds/model-picker': { title: 'Model picker', part: false },
		'gds/sticky-cart': { title: 'Sticky add to cart', part: false },
		'core/paragraph': { title: 'Paragraph', part: false },
		'core/columns': { title: 'Columns', part: false },
		'core/block': { title: 'Pattern', part: false },
	},
	synced: { '12': 'Works with' },
};

describe( 'blockOutline', () => {
	it( 'names the top-level blocks and what is inside them, a synced pattern by its title', () => {
		const markup = [
			'<!-- wp:antijante-woocommerce/woo-product --><!-- wp:woocommerce/single-product {"productId":95} --><!-- wp:columns --><div>',
			'<!-- wp:gds/gallery-carousel /--><!-- wp:woocommerce/product-price /--><!-- wp:gds/model-picker {"a":{"b":1}} /-->',
			'</div><!-- /wp:columns --><!-- /wp:woocommerce/single-product --><!-- /wp:antijante-woocommerce/woo-product -->',
			'<!-- wp:gds/sticky-cart /-->',
			'<!-- wp:block {"ref":12} /-->',
			'<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>b</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>c</p><!-- /wp:paragraph -->',
		].join( '\n' );

		expect( blockOutline( markup, titles ) ).toEqual( [
			{ title: 'Product', inside: [ 'Gallery carousel', 'Model picker' ] },
			{ title: 'Sticky add to cart', inside: [] },
			{ title: 'Synced section: Works with', inside: [] },
			{ title: 'Paragraph (3)', inside: [] },
		] );
	} );

	it( 'is empty for text without blocks', () => {
		expect( blockOutline( '<p>Just text</p>', titles ) ).toEqual( [] );
	} );
} );
