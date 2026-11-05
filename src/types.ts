export type LinkType = 'email' | 'phone' | 'address';

export interface Ring {
	key: number;
	size: number;
	sample: boolean;
	links: LinkType[];
	orders: { id: number; url: string }[];
	total: string;
	last_order: string | null;
}

export interface Stats {
	indexed: number;
	rings: number;
	ring_customers: number;
	orders_in_rings: number;
	blocked_30d: number;
	customers: number;
	coupons: number;
	sample: boolean;
	version: string;
}

export interface Overview {
	stats: Stats;
	rings: Ring[];
	total: number;
	total_pages: number;
}

export interface Filters {
	search: string;
	min_size: number;
	link: '' | LinkType;
	page: number;
}

export type Tab = 'overview' | 'accounts' | 'rules' | 'activity' | 'settings';

export interface RingOrder {
	id: number;
	edge_id: number;
	url: string;
	total: string;
	date: string | null;
	status: string;
	coupons: string[];
}

export interface RingHub {
	id: string;
	type: LinkType;
	count: number;
	top: boolean;
}

export interface RingDetail {
	key: number;
	cluster: string;
	blocked: boolean;
	allowed: boolean;
	attempts: number;
	prevented: string;
	size: number;
	sample: boolean;
	links: LinkType[];
	personas: number;
	total: string;
	first: string | null;
	orders: RingOrder[];
	hubs: RingHub[];
	edges: [ number, string ][];
}

export type EventType = 'blocked' | 'clean' | 'ring' | 'held' | 'import' | 'rule';

export interface OrderRef {
	id: number;
	url: string;
	date: string | null;
}

export interface ActivityEvent {
	id: number;
	type: EventType;
	created: string;
	coupon: string;
	order: OrderRef | null;
	linked: OrderRef[];
	matched: LinkType[];
	amount: string;
	ring: { key: number; size: number; blocked: boolean; allowed: boolean } | null;
	meta: { why?: string; a?: string; orders?: number; rings?: number };
}

export interface ActivityStats {
	total: number;
	evaluated: number;
	coupons: number;
	blocked: number;
	saved: string;
	cleared: number;
}

export interface Activity {
	events: ActivityEvent[];
	total: number;
	total_pages: number;
	stats: ActivityStats;
}

export interface ActivityFilters {
	range: '24h' | '7d' | '30d';
	search: string;
	type: string;
	severity: string;
	page: number;
	per_page: number;
}
