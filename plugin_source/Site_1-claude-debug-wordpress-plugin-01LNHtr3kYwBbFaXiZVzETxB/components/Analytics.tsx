
import React from 'react';
import { TrendingUp, Users, Eye, MapPin } from 'lucide-react';

const Analytics: React.FC = () => {
  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold text-slate-900">Аналитика платформы</h1>
      
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-64 flex flex-col justify-center items-center text-center">
          <div className="p-4 bg-blue-50 text-blue-600 rounded-full mb-4">
            <Eye size={32} />
          </div>
          <h3 className="text-4xl font-bold text-slate-900">142.5K</h3>
          <p className="text-slate-500 mt-2">Просмотров за неделю</p>
          <span className="text-green-600 text-sm font-medium mt-1">+12.5% рост</span>
        </div>

        <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-64 flex flex-col justify-center items-center text-center">
          <div className="p-4 bg-purple-50 text-purple-600 rounded-full mb-4">
             <Users size={32} />
          </div>
          <h3 className="text-4xl font-bold text-slate-900">24.1K</h3>
          <p className="text-slate-500 mt-2">Активная аудитория</p>
          <span className="text-green-600 text-sm font-medium mt-1">+5.2% рост</span>
        </div>

        <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-64 flex flex-col justify-center items-center text-center">
          <div className="p-4 bg-orange-50 text-orange-600 rounded-full mb-4">
             <MapPin size={32} />
          </div>
          <h3 className="text-4xl font-bold text-slate-900">München</h3>
          <p className="text-slate-500 mt-2">Топ регион</p>
          <span className="text-slate-400 text-sm font-medium mt-1">68% трафика</span>
        </div>
      </div>

      <div className="bg-white p-8 rounded-xl shadow-sm border border-slate-200 text-center py-20">
        <TrendingUp size={48} className="text-slate-300 mx-auto mb-4" />
        <h3 className="text-lg font-medium text-slate-900">Детальные графики загружаются...</h3>
        <p className="text-slate-500">Модуль аналитики будет подключен к Google Analytics 4.</p>
      </div>
    </div>
  );
};

export default Analytics;
