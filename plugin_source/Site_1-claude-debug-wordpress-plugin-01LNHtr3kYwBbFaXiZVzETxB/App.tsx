
import React, { useState } from 'react';
import Layout from './components/Layout';
import Dashboard from './components/Dashboard';
import CreateArticle from './components/CreateArticle';
import Login from './components/Login';
import Settings from './components/Settings';
import Analytics from './components/Analytics';
import CalendarView from './components/CalendarView';
import Moderation from './components/Moderation';
import PodcastView from './components/PodcastView';
import { User } from './types';
import { mockUsers } from './mockData';

const App: React.FC = () => {
  // State for Auth
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [user, setUser] = useState<User | null>(null);
  
  // State for Navigation
  const [activeTab, setActiveTab] = useState('dashboard');

  const handleLogin = () => {
    setIsLoggedIn(true);
    setUser(mockUsers[0]); // Log in as first mock user (Admin)
  };

  const handleLogout = () => {
    setIsLoggedIn(false);
    setUser(null);
  };

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <Dashboard />;
      case 'create':
        return <CreateArticle />;
      case 'analytics':
        return <Analytics />;
      case 'settings':
        return <Settings />;
      case 'calendar':
        return <CalendarView />;
      case 'moderation':
        return <Moderation />;
      case 'podcast':
        return <PodcastView />;
      default:
        return <Dashboard />;
    }
  };

  if (!isLoggedIn) {
    return <Login onLogin={handleLogin} />;
  }

  return (
    <Layout 
      activeTab={activeTab} 
      setActiveTab={setActiveTab} 
      user={user}
      onLogout={handleLogout}
    >
      {renderContent()}
    </Layout>
  );
};

export default App;
