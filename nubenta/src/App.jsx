import React from "react";

function App() {
  return (
    <div className="min-h-screen bg-gray-900 text-white flex flex-col items-center justify-center px-4">
      <header className="text-center">
        <h1 className="text-5xl font-extrabold mb-4 tracking-tight text-purple-400">
          Nubenta
        </h1>
        <p className="text-xl text-gray-300 mb-8">
          Social Media of the 90s, today.
        </p>
        <div className="flex gap-4 justify-center">
          <button className="bg-purple-600 hover:bg-purple-700 px-6 py-2 rounded-xl text-white transition">
            Sign In
          </button>
          <button className="bg-gray-800 border border-purple-600 hover:bg-gray-700 px-6 py-2 rounded-xl text-purple-300 transition">
            Sign Up
          </button>
        </div>
      </header>

      <footer className="absolute bottom-4 text-xs text-gray-500">
        &copy; {new Date().getFullYear()} Nubenta. All rights reserved.
      </footer>
    </div>
  );
}

export default App;
