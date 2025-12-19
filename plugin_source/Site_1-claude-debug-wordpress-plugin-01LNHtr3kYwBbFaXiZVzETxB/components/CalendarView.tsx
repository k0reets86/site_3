
import React, { useState } from 'react';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight, Plus } from 'lucide-react';

const CalendarView: React.FC = () => {
  const [currentDate, setCurrentDate] = useState(new Date());

  const nextMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1));
  };

  const prevMonth = () => {
    setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1));
  };

  const getDaysInMonth = (date: Date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const days = new Date(year, month + 1, 0).getDate();
    return Array.from({ length: days }, (_, i) => i + 1);
  };

  const getFirstDayOfMonth = (date: Date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    // getDay() returns 0 for Sunday. We want 0 for Monday.
    const dayOfWeek = new Date(year, month, 1).getDay();
    return dayOfWeek === 0 ? 6 : dayOfWeek - 1;
  };

  const monthNames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];
  const daysOfWeek = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

  const days = getDaysInMonth(currentDate);
  const offset = getFirstDayOfMonth(currentDate);
  const totalSlots = days.length + offset;
  // Calculate extra slots needed to fill the last row
  const padding = (7 - (totalSlots % 7)) % 7;

  return (
    <div className="space-y-6 h-full flex flex-col max-h-[calc(100vh-120px)]">
      <div className="flex items-center justify-between shrink-0">
        <div>
            <h1 className="text-2xl font-bold text-slate-900">Календарь публикаций</h1>
            <p className="text-slate-500">Планирование контент-плана и важных дат.</p>
        </div>
        <div className="flex items-center gap-4 bg-white p-1.5 rounded-xl border border-slate-200 shadow-sm">
            <button onClick={prevMonth} className="p-2 hover:bg-slate-100 rounded-lg text-slate-600"><ChevronLeft size={20} /></button>
            <span className="font-bold text-slate-800 w-40 text-center">{monthNames[currentDate.getMonth()]} {currentDate.getFullYear()}</span>
            <button onClick={nextMonth} className="p-2 hover:bg-slate-100 rounded-lg text-slate-600"><ChevronRight size={20} /></button>
        </div>
      </div>

      <div className="bg-white rounded-2xl shadow-sm border border-slate-200 flex-1 flex flex-col overflow-hidden">
        {/* Days Header */}
        <div className="grid grid-cols-7 border-b border-slate-200 bg-slate-50 shrink-0">
            {daysOfWeek.map(day => (
                <div key={day} className="py-4 text-center text-sm font-bold text-slate-500 uppercase tracking-wider">
                    {day}
                </div>
            ))}
        </div>
        
        {/* Calendar Grid (Scrollable) */}
        <div className="grid grid-cols-7 flex-1 overflow-y-auto divide-x divide-slate-100 divide-y">
            {/* Offset */}
            {Array.from({ length: offset }).map((_, i) => (
                <div key={`empty-${i}`} className="bg-slate-50/30 min-h-[120px]" />
            ))}

            {/* Actual Days */}
            {days.map((day) => {
                const isToday = 
                    day === new Date().getDate() && 
                    currentDate.getMonth() === new Date().getMonth() &&
                    currentDate.getFullYear() === new Date().getFullYear();

                return (
                    <div key={day} className="p-2 relative hover:bg-slate-50 transition-colors group min-h-[120px] flex flex-col">
                        <span className={`text-sm font-medium w-7 h-7 flex items-center justify-center rounded-full mb-2 ${isToday ? 'bg-blue-600 text-white shadow-md' : 'text-slate-700'}`}>
                            {day}
                        </span>
                        
                        <div className="space-y-1.5 flex-1">
                            {/* Mock Events logic based on day number */}
                            {(day % 7 === 2) && (
                                <div className="p-1.5 bg-blue-100 text-blue-800 text-xs font-medium rounded border border-blue-200 truncate cursor-pointer hover:bg-blue-200 transition-colors">
                                    📰 MVG News
                                </div>
                            )}
                            {(day % 10 === 0) && (
                                <div className="p-1.5 bg-purple-100 text-purple-800 text-xs font-medium rounded border border-purple-200 truncate cursor-pointer hover:bg-purple-200 transition-colors">
                                    🎙️ Подкаст
                                </div>
                            )}
                        </div>

                        <button className="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 p-1.5 hover:bg-blue-50 text-blue-600 rounded transition-all">
                            <Plus size={16} />
                        </button>
                    </div>
                );
            })}

            {/* Padding */}
            {Array.from({ length: padding }).map((_, i) => (
                <div key={`pad-${i}`} className="bg-slate-50/30 min-h-[120px]" />
            ))}
        </div>
      </div>
    </div>
  );
};

export default CalendarView;
