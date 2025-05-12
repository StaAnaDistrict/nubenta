import React from 'react';

const Homepage = () => {
  return (
    <div className="min-h-screen bg-gray-100 flex flex-col">
      <header className="bg-blue-600 text-white p-4">
        <h1 className="text-3xl font-bold">Welcome to Nubenta</h1>
      </header>

      <main className="flex-grow p-6">
        <h2 className="text-2xl font-semibold text-center mb-4">Your App's Purpose</h2>
        <p className="text-lg text-center">
          Here you can describe the purpose of your app and what users can expect.
        </p>
      </main>

      <footer className="bg-blue-600 text-white p-4 text-center">
        <p>© 2025 Nubenta. All rights reserved.</p>
      </footer>
    </div>
  );
};

export default Homepage;
