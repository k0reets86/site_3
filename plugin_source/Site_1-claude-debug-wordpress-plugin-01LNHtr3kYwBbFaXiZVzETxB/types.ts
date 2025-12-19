
export type Language = 'de' | 'ua' | 'ru' | 'en';

export enum ArticleStatus {
  PENDING_OK = 'PENDING_OK',
  AUTO_READY = 'AUTO_READY',
  SCHEDULED = 'SCHEDULED',
  PUBLISHED = 'PUBLISHED',
  REJECTED = 'REJECTED',
  DRAFT = 'DRAFT',
  PROCESSING = 'PROCESSING'
}

export type SourceCategory = 'official' | 'media' | 'social' | 'international' | 'ukraine' | 'emergency';

export interface Source {
  id: string;
  name: string;
  trustScore: number;
  url: string;
  category: SourceCategory;
  active: boolean;
  lastFetched?: string;
}

export interface User {
  id: string;
  name: string;
  email: string;
  role: 'admin' | 'editor';
  status: 'active' | 'invited';
  avatar?: string;
}

export interface CustomApiKey {
  id: string;
  serviceName: string;
  key: string;
  addedAt: string;
}

export interface PodcastEpisode {
  id: string;
  date: string;
  title: string;
  duration: string;
  status: 'ready' | 'processing';
  topics: string[];
}

export interface Article {
  id: string;
  eventId: string;
  status: ArticleStatus;
  createdAt: string;
  titles: Record<Language, string>;
  source: Source;
  category: string;
  riskFlags: string[];
  factCheckScore: number;
  predictedCtr?: number;
  readTime?: string;
  imageUrl?: string;
  content?: {
    lead: Record<Language, string>;
    body: Record<Language, string>;
  };
}

export interface StatMetric {
  label: string;
  value: string;
  change: number; // percentage
  trend: 'up' | 'down' | 'neutral';
}
