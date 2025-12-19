
import React, { useState } from 'react';
import { mockArticles } from '../mockData';
import { Article } from '../types';
import QueueTable from './QueueTable';
import ArticlePreview from './ArticlePreview';
import { TrendingUp, Users, Eye, Share2, ArrowUpRight, Filter } from 'lucide-react';

const Dashboard: React.FC = () => {
  const [selectedArticle, setSelectedArticle] = useState<Article | null>(null);
  const [filter, setFilter] = useState('all');

  // Translation mapping for filters
  const filterLabels: { [key: string]: string } = {
    'All': 'Все',
    'Needs Approval (12)': 'Требуют проверки (12)',
    'Auto-Ready (8)': 'Авто-публикация (8)',
    'Scheduled': 'Запланировано',
    'Published': 'Опубликовано'
  };

  const filters = ['All', 'Needs Approval (12)', 'Auto-Ready (8)', 'Scheduled', 'Published'];

  return (
    <div className="space-y-8">
      
      {/* Header Section */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Очередь контента</h1>
          <p className="text-slate-500">Управление входящими сигналами и AI черновиками.</p>
        </div>
        <div className="flex items-center gap-2">
           <span className="text-sm text-slate-500 mr-2">Синхронизация: 1 мин назад</span>
           <button className="p-2 bg-white border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 shadow-sm">
             <Filter size={18} />
           </button>
        </div>
      </div>

      {/* Metrics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {[
            { label: 'Просмотры (24ч)', value: '45.2K', icon: Eye, color: 'blue', change: '+12%' },
            { label: 'Ср. CTR', value: '4.8%', icon: TrendingUp, color: 'green', change: '+0.3%' },
            { label: 'Активные читатели', value: '1,204', icon: Users, color: 'purple', change: '+8%' },
            { label: 'Репосты', value: '324', icon: Share2, color: 'orange', change: '+15%' },
        ].map((stat, idx) => (
            <div key={idx} className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between h-32">
                <div className="flex justify-between items-start">
                    <div className={`p-2 rounded-lg bg-${stat.color}-50 text-${stat.color}-600`}>
                        <stat.icon size={20} />
                    </div>
                    <span className="flex items-center text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                        {stat.change} <ArrowUpRight size={12} className="ml-1" />
                    </span>
                </div>
                <div>
                    <h3 className="text-2xl font-bold text-slate-900">{stat.value}</h3>
                    <p className="text-xs text-slate-500 font-medium uppercase tracking-wide mt-1">{stat.label}</p>
                </div>
            </div>
        ))}
      </div>

      {/* Filters */}
      <div className="flex gap-2 overflow-x-auto pb-2">
        {filters.map((f) => (
          <button 
            key={f}
            onClick={() => setFilter(f.toLowerCase())}
            className={`px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-all ${
              filter === f.toLowerCase() || (filter === 'all' && f === 'All')
                ? 'bg-slate-900 text-white shadow-md' 
                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
            }`}
          >
            {filterLabels[f]}
          </button>
        ))}
      </div>

      {/* Queue Table */}
      <QueueTable 
        articles={mockArticles} 
        onSelectArticle={setSelectedArticle} 
      />

      {/* Preview Modal */}
      {selectedArticle && (
        <ArticlePreview 
          article={selectedArticle} 
          onClose={() => setSelectedArticle(null)} 
        />
      )}
    </div>
  );
};

export default Dashboard;
