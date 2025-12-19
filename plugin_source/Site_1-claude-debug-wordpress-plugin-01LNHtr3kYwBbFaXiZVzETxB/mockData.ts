
import { Article, ArticleStatus, PodcastEpisode, User } from './types';

// Stable Unsplash Images for reliability
const IMAGES = {
  politics: 'https://images.unsplash.com/photo-1541872703-74c5963631df?auto=format&fit=crop&w=800&q=80',
  transport: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=800&q=80',
  money: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80',
  city: 'https://images.unsplash.com/photo-1534313314376-a72289b6181e?auto=format&fit=crop&w=800&q=80'
};

export const mockArticles: Article[] = [
  {
    id: 'draft_1',
    eventId: 'evt_001',
    status: ArticleStatus.PENDING_OK,
    createdAt: '2023-10-24T14:32:00',
    titles: {
      de: 'Neue Regelung für Aufenthaltstitel ab Dezember',
      ua: 'Нові правила для посвідок на проживання з грудня',
      ru: 'Новые правила вида на жительство с декабря',
      en: 'New Residence Permit Rules Starting December'
    },
    source: { 
      id: 'bamf', 
      name: 'BAMF Press', 
      trustScore: 0.92,
      url: 'https://bamf.de',
      category: 'official',
      active: true
    },
    category: 'Politik',
    riskFlags: ['politics', 'sensitive'],
    factCheckScore: 0.92,
    predictedCtr: 5.4,
    readTime: '4 min',
    imageUrl: IMAGES.politics,
    content: {
      lead: {
        de: 'Ab dem 1. Dezember treten wichtige Änderungen für ukrainische Geflüchtete in Kraft.',
        ua: 'З 1 грудня набувають чинності важливі зміни для українських біженців.',
        ru: 'С 1 декабря вступают в силу важные изменения для украинских беженцев.',
        en: 'Starting December 1st, important changes for Ukrainian refugees come into effect.'
      },
      body: {
        de: '<p>Das Bundesamt für Migration und Flüchtlinge (BAMF) hat heute neue Richtlinien veröffentlicht. Die wichtigste Änderung betrifft die Verlängerung von...</p><ul><li>Automatische Verlängerung</li><li>Neue Dokumente</li></ul>',
        ua: '<p>Федеральне відомство з питань міграції та біженців (BAMF) сьогодні опублікувало нові правила...</p>',
        ru: '<p>Федеральное ведомство по вопросам миграции и беженцев (BAMF) сегодня опубликовало новые правила...</p>',
        en: '<p>The Federal Office for Migration and Refugees (BAMF) published new guidelines today...</p>'
      }
    }
  },
  {
    id: 'draft_2',
    eventId: 'evt_002',
    status: ArticleStatus.AUTO_READY,
    createdAt: '2023-10-24T14:15:00',
    titles: {
      de: 'MVG erhöht Preise ab 1. November',
      ua: 'МВГ підвищує ціни на проїзд з 1 листопада',
      ru: 'MVG повышает цены на проезд с 1 ноября',
      en: 'MVG Raises Prices Starting November 1st'
    },
    source: { 
      id: 'mvg', 
      name: 'MVG Official', 
      trustScore: 0.85,
      url: 'https://mvg.de',
      category: 'official',
      active: true
    },
    category: 'Lokales',
    riskFlags: [],
    factCheckScore: 0.98,
    predictedCtr: 6.1,
    readTime: '2 min',
    imageUrl: IMAGES.transport,
    content: {
        lead: { de: 'Die Münchner Verkehrsgesellschaft passt die Tarife an.', ua: 'Мюнхенська транспортна компанія коригує тарифи.', ru: 'Мюнхенская транспортная компания корректирует тарифы.', en: 'The Munich Transport Company is adjusting tariffs.' },
        body: { de: '<p>Die Preise steigen um durchschnittlich 3%.</p>', ua: '<p>Ціни зростуть в середньому на 3%.</p>', ru: '<p>Цены вырастут в среднем на 3%.</p>', en: '<p>Prices will rise by an average of 3%.</p>' }
    }
  },
  {
    id: 'draft_3',
    eventId: 'evt_003',
    status: ArticleStatus.PROCESSING,
    createdAt: '2023-10-24T14:40:00',
    titles: {
      de: 'Processing incoming signal...',
      ua: 'Обробка вхідного сигналу...',
      ru: 'Обработка входящего сигнала...',
      en: 'Processing incoming signal...'
    },
    source: { 
      id: 'zeitung', 
      name: 'Süddeutsche', 
      trustScore: 0.78,
      url: 'https://sueddeutsche.de',
      category: 'media',
      active: true
    },
    category: 'Wirtschaft',
    riskFlags: [],
    factCheckScore: 0,
    imageUrl: IMAGES.city,
    content: {
        lead: { de: '', ua: '', ru: '', en: '' },
        body: { de: '', ua: '', ru: '', en: '' }
    }
  }
];

export const mockPodcasts: PodcastEpisode[] = [
  {
    id: 'pod_1',
    date: '24.10.2023',
    title: 'Вечірній бріфінг: Мюнхен сьогодні',
    duration: '12:34',
    status: 'ready',
    topics: ['MVG Preise', 'BAMF News', 'Oktoberfest']
  },
  {
    id: 'pod_2',
    date: '23.10.2023',
    title: 'Підсумки дня: Баварія',
    duration: '10:15',
    status: 'ready',
    topics: ['S-Bahn Stammstrecke', 'Jobcenter']
  }
];

export const mockUsers: User[] = [
  { id: 'u1', name: 'Alex Admin', email: 'alex@news.de', role: 'admin', status: 'active' },
  { id: 'u2', name: 'Maria Editor', email: 'maria@news.de', role: 'editor', status: 'active' },
  { id: 'u3', name: 'Oleg Writer', email: 'oleg@news.de', role: 'editor', status: 'invited' },
];
