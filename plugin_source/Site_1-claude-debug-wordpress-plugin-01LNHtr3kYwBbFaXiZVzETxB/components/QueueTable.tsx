
import React from 'react';
import { Article, ArticleStatus } from '../types';
import { 
  CheckCircle2, 
  AlertCircle, 
  Clock, 
  MoreHorizontal, 
  FileText,
  ShieldAlert,
  ExternalLink
} from 'lucide-react';

interface QueueTableProps {
  articles: Article[];
  onSelectArticle: (article: Article) => void;
}

const QueueTable: React.FC<QueueTableProps> = ({ articles, onSelectArticle }) => {
  
  const getStatusBadge = (status: ArticleStatus) => {
    switch (status) {
      case ArticleStatus.PENDING_OK:
        return <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><AlertCircle size={12} /> Требует проверки</span>;
      case ArticleStatus.AUTO_READY:
        return <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"><Clock size={12} /> Авто-публикация</span>;
      case ArticleStatus.PROCESSING:
        return <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 animate-pulse"><FileText size={12} /> AI Обработка</span>;
      default:
        return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">{status}</span>;
    }
  };

  return (
    <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider font-semibold">
              <th className="px-6 py-4">Время / Статус</th>
              <th className="px-6 py-4 w-1/2">Заголовок (DE / UA)</th>
              <th className="px-6 py-4">Источник и Доверие</th>
              <th className="px-6 py-4">Флаги риска</th>
              <th className="px-6 py-4 text-right">Действия</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {articles.map((article) => (
              <tr 
                key={article.id} 
                onClick={() => onSelectArticle(article)}
                className="hover:bg-slate-50 cursor-pointer transition-colors duration-150 group"
              >
                <td className="px-6 py-4 whitespace-nowrap">
                  <div className="flex flex-col gap-1">
                    <span className="text-sm font-medium text-slate-700">
                        {new Date(article.createdAt).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                    </span>
                    {getStatusBadge(article.status)}
                  </div>
                </td>
                <td className="px-6 py-4">
                    <div className="space-y-1">
                        <p className="text-sm font-semibold text-slate-900 line-clamp-1">{article.titles.de}</p>
                        <p className="text-sm text-slate-500 line-clamp-1 font-medium">{article.titles.ua}</p>
                    </div>
                </td>
                <td className="px-6 py-4">
                  <div className="flex items-center gap-2">
                    <div className="flex flex-col">
                        <span className="text-sm font-medium text-slate-700">{article.source.name}</span>
                        <div className="flex items-center gap-1">
                            <div className="w-16 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                <div 
                                    className={`h-full rounded-full ${article.source.trustScore > 0.9 ? 'bg-green-500' : 'bg-amber-500'}`} 
                                    style={{ width: `${article.source.trustScore * 100}%` }}
                                />
                            </div>
                            <span className="text-xs text-slate-400">{article.source.trustScore}</span>
                        </div>
                    </div>
                  </div>
                </td>
                <td className="px-6 py-4">
                  <div className="flex flex-wrap gap-1">
                    {article.riskFlags.length > 0 ? article.riskFlags.map(flag => (
                        <span key={flag} className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">
                           {flag === 'sensitive' && <ShieldAlert size={10} className="mr-1"/>}
                           {flag === 'sensitive' ? 'чувствительно' : flag === 'politics' ? 'политика' : flag}
                        </span>
                    )) : <span className="text-xs text-slate-400 italic">Нет</span>}
                  </div>
                </td>
                <td className="px-6 py-4 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                  <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button className="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                        <ExternalLink size={18} />
                    </button>
                    <button className="px-3 py-1.5 bg-slate-900 text-white text-xs font-medium rounded-lg hover:bg-slate-800 transition-colors">
                        Обзор
                    </button>
                    <button className="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                        <MoreHorizontal size={18} />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default QueueTable;
