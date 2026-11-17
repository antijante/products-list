/**
 * wp.hooks names. Filters receive and return the value named; actions get
 * the payload named. See docs/contracts.md §8 and docs/extension-api.md.
 */
export const HOOK_NAMESPACE = 'antijanteProductsList';

export const FILTERS = {
	/** ( fields: ProductField[], settings: Settings ) => ProductField[] */
	fields: 'antijanteProductsList.fields',
	/** ( actions: ProductAction[], settings: Settings ) => ProductAction[] */
	actions: 'antijanteProductsList.actions',
	/** ( params: QueryParams, context: QueryContext ) => QueryParams — the wc/v3 products list request */
	query: 'antijanteProductsList.query',
	/** ( params: QueryParams, context: { parentId: number; page: number } ) => QueryParams */
	variationsQuery: 'antijanteProductsList.variationsQuery',
	/** ( item: ProductListItem ) => ProductListItem — every row after fetch, before cache */
	item: 'antijanteProductsList.item',
	/** ( payload: Record<string, unknown>, item: ProductListItem, edits: Record<string, unknown> ) => payload */
	savePayload: 'antijanteProductsList.savePayload',
	/** ( view: View, settings: Settings ) => View */
	defaultView: 'antijanteProductsList.defaultView',
	/** ( tabs: StatusTab[], counts: Counts ) => StatusTab[] */
	statusTabs: 'antijanteProductsList.statusTabs',
	/** ( tabs: QuickEditTab[], items: ProductListItem[] ) => QuickEditTab[] */
	quickEditTabs: 'antijanteProductsList.quickEdit.tabs',
	/** ( layout: Form, tab: QuickEditTab, items: ProductListItem[] ) => Form */
	quickEditLayout: 'antijanteProductsList.quickEdit.layout',
	/** ( fieldIds: string[] ) => string[] — fields that get the set/increase/decrease control */
	bulkNumericFields: 'antijanteProductsList.bulkNumericFields',
} as const;

export const ACTIONS = {
	/** ( api: ExtensionApi ) — window.antijanteProductsList exists, before mount */
	ready: 'antijanteProductsList.ready',
	/** ( items: ProductListItem[], context: { tab: string; view: View; total: number } ) */
	loaded: 'antijanteProductsList.loaded',
	/** ( result: BatchResult, context: { source: string } ) */
	saved: 'antijanteProductsList.saved',
	/** ( ids: number[], context: { action: 'trash' | 'delete'; batchId: string } ) */
	deleted: 'antijanteProductsList.deleted',
	/**
	 * ( result: { action: string; ids: number[]; batchId: string; items: ProductListItem[] } )
	 * — a declarative (server) action finished; `ids` are the rows it
	 * processed without error, `items` the refreshed rows it returned.
	 */
	actionPerformed: 'antijanteProductsList.actionPerformed',
} as const;

export type FilterName = ( typeof FILTERS )[ keyof typeof FILTERS ];

export type ActionName = ( typeof ACTIONS )[ keyof typeof ACTIONS ];

/**
 * The namespace an extension should pass as the third argument of
 * `addFilter`/`addAction` when it has no better one: `hookNamespace( 'gds-woo-i18n' )`
 * → `gds-woo-i18n/wc-products-list`.
 */
export function hookNamespace( extensionId: string ): string {
	return `${ extensionId }/wc-products-list`;
}
